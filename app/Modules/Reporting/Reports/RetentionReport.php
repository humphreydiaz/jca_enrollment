<?php

namespace App\Modules\Reporting\Reports;

use App\Modules\Academics\Models\AcademicYear;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * D-066 retention report (pre-final panel: "ilan yung bumabagsak, ilan yung nag shishift, ilan yung nag
 * drodropout/kick out, yung mga nag enrolled pero di naman bumalik / transfers must be also in detailed").
 *
 * One row per learner and outcome for the chosen school year. Rows are built from enrollment, exit,
 * section-assignment and final-grade records; nothing is inferred beyond what those records say.
 * "Below 75" uses the DepEd numeric threshold as a plain fact; it is not the school's promotion rule (P-010).
 */
class RetentionReport
{
    public const OUTCOMES = [
        'dropped_out' => 'Dropped out',
        'excluded' => 'Excluded / expelled',
        'other_drop' => 'Dropped (other reason)',
        'dropped_uncategorised' => 'Dropped (kind not recorded)',
        'transferred_out' => 'Transferred out',
        'not_returned' => 'Did not return this year',
        'repeating' => 'Repeating the same grade',
        'changed_section' => 'Changed section',
        'below_75' => 'Final grade below 75',
    ];

    /** Enrollment statuses that count as having been enrolled in a year. */
    private const ENROLLED = ['approved', 'enrolled'];

    /** Enrollment statuses that mean a learner applied or attended in a year (not abandoned drafts). */
    private const ATTENDED = ['submitted', 'under_review', 'assessment_pending', 'approved', 'enrolled', 'incomplete', 'withdrawn'];

    public function year(array $filters): ?AcademicYear
    {
        if (filled($filters['school_year'] ?? null)) {
            return AcademicYear::query()->where('public_id', $filters['school_year'])->first();
        }

        return AcademicYear::query()->where('status', 'open')->orderByDesc('starts_on')->first()
            ?? AcademicYear::query()->orderByDesc('starts_on')->first();
    }

    public function previous(AcademicYear $year): ?AcademicYear
    {
        return AcademicYear::query()->where('starts_on', '<', $year->starts_on)->orderByDesc('starts_on')->first();
    }

    /** All rows for the year as one query aliased `rt`, ready for the catalog's filters and sorting. */
    public function query(array $filters): Builder
    {
        $year = $this->year($filters);
        if ($year === null) {
            return DB::query()->fromSub(DB::table('enrollments')->selectRaw('NULL as row_key')->whereRaw('1 = 0'), 'rt');
        }
        $previous = $this->previous($year);

        $union = $this->exits($year)->unionAll($this->changedSection($year))->unionAll($this->belowSeventyFive($year));
        if ($previous !== null) {
            $union->unionAll($this->notReturned($year, $previous))->unionAll($this->repeating($year, $previous));
        }

        return DB::query()->fromSub($union, 'rt')->select('rt.*')->orderBy('rt.outcome')->orderBy('rt.student_name');
    }

    /**
     * Counts for the summary above the rows.
     *
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $year = $this->year($filters);
        if ($year === null) {
            return ['year' => null];
        }
        $previous = $this->previous($year);
        $counts = DB::query()->fromSub($this->query($filters), 'r')->groupBy('outcome')->selectRaw('outcome, COUNT(*) as total')->pluck('total', 'outcome');

        $continuing = $eligible = $graduated = null;
        if ($previous !== null) {
            $lastYear = DB::table('enrollments as e')->join('student_profiles as s', 's.id', '=', 'e.student_id')
                ->where('e.academic_year_id', $previous->id)->whereIn('e.status', self::ENROLLED);
            $graduated = (clone $lastYear)->where('s.status', 'graduated')->distinct()->count('e.student_id');
            $eligible = (clone $lastYear)->whereNotIn('s.status', ['graduated'])->distinct()->count('e.student_id');
            $continuing = (clone $lastYear)->whereNotIn('s.status', ['graduated'])
                ->whereExists(fn ($q) => $q->selectRaw('1')->from('enrollments as now')->whereColumn('now.student_id', 'e.student_id')
                    ->where('now.academic_year_id', $year->id)->whereIn('now.status', self::ATTENDED))
                ->distinct()->count('e.student_id');
        }

        return [
            'year' => $year,
            'previous' => $previous,
            'enrolled' => DB::table('enrollments')->where('academic_year_id', $year->id)->whereIn('status', self::ENROLLED)->count(),
            'counts' => collect(self::OUTCOMES)->map(fn ($label, $key) => (int) ($counts[$key] ?? 0))->all(),
            'continuing' => $continuing,
            'eligible' => $eligible,
            'graduated' => $graduated,
            'retention_rate' => $eligible ? round($continuing / $eligible * 100, 1) : null,
        ];
    }

    /** The shared columns every part selects, from an enrollment `e`, learner `s`, year `ay`, grade `g`, section `sec`. */
    private function base(Builder $query, AcademicYear $reportYear, string $outcomeSql, string $detailsSql, string $dateSql): Builder
    {
        return $query
            ->join('student_profiles as s', 's.id', '=', 'e.student_id')
            ->join('academic_years as ay', 'ay.id', '=', 'e.academic_year_id')
            ->join('grade_levels as g', 'g.id', '=', 'e.grade_level_id')
            ->leftJoin('section_assignments as sa', fn ($join) => $join->on('sa.enrollment_id', '=', 'e.id')->where('sa.status', 'confirmed')->whereNull('sa.ended_at'))
            ->leftJoin('sections as sec', 'sec.id', '=', 'sa.section_id')
            ->selectRaw("s.student_number, CONCAT(s.last_name, ', ', s.first_name) as student_name, ay.name as school_year, g.name as grade, sec.name as section, ({$outcomeSql}) as outcome, ({$detailsSql}) as details, ({$dateSql}) as outcome_date, ? as year_public, g.public_id as grade_public, sec.public_id as section_public, s.first_name, s.last_name, CONCAT(e.public_id, '.', ({$outcomeSql})) as row_key", [$reportYear->public_id]);
    }

    /** A properly quoted SQL string literal (year names are admin-entered text). */
    private function literal(string $text): string
    {
        return DB::connection()->getPdo()->quote($text);
    }

    private function exits(AcademicYear $year): Builder
    {
        $outcome = "CASE WHEN s.status = 'transferred_out' THEN 'transferred_out' WHEN s.exit_category IN ('dropped_out','excluded','other_drop') THEN s.exit_category ELSE 'dropped_uncategorised' END";
        $details = "CASE WHEN s.status = 'transferred_out' THEN CONCAT('To ', COALESCE(s.exit_receiving_school, 'receiving school not recorded')) ELSE 'Recorded as dropped in DepEd LIS' END";

        return $this->base(DB::table('enrollments as e'), $year, $outcome, $details, 'DATE(COALESCE(s.transferred_out_at, s.dropped_at, s.archived_at))')
            ->where('e.academic_year_id', $year->id)->whereIn('e.status', self::ATTENDED)
            ->whereIn('s.status', ['transferred_out', 'dropped']);
    }

    private function notReturned(AcademicYear $year, AcademicYear $previous): Builder
    {
        return $this->base(DB::table('enrollments as e'), $year, "'not_returned'", $this->literal('No enrollment for '.$year->name), 'NULL')
            ->where('e.academic_year_id', $previous->id)->whereIn('e.status', self::ENROLLED)
            ->whereNotIn('s.status', ['graduated', 'transferred_out', 'dropped'])
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('enrollments as now')->whereColumn('now.student_id', 'e.student_id')
                ->where('now.academic_year_id', $year->id)->whereIn('now.status', self::ATTENDED));
    }

    private function repeating(AcademicYear $year, AcademicYear $previous): Builder
    {
        return $this->base(DB::table('enrollments as e'), $year, "'repeating'", $this->literal('Same grade level as '.$previous->name), 'NULL')
            ->where('e.academic_year_id', $year->id)->whereIn('e.status', self::ENROLLED)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('enrollments as before')->whereColumn('before.student_id', 'e.student_id')
                ->whereColumn('before.grade_level_id', 'e.grade_level_id')->where('before.academic_year_id', $previous->id)
                ->whereIn('before.status', self::ENROLLED));
    }

    private function changedSection(AcademicYear $year): Builder
    {
        $from = '(SELECT s2.name FROM section_assignments sa2 JOIN sections s2 ON s2.id = sa2.section_id WHERE sa2.enrollment_id = e.id AND sa2.ended_at IS NOT NULL ORDER BY sa2.effective_from, sa2.id LIMIT 1)';

        return $this->base(DB::table('enrollments as e'), $year, "'changed_section'", "CONCAT('Moved from ', COALESCE({$from}, 'an earlier section'), ' to ', COALESCE(sec.name, 'no current section'))", 'DATE(sa.effective_from)')
            ->where('e.academic_year_id', $year->id)->whereIn('e.status', self::ENROLLED)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('section_assignments as moved')->whereColumn('moved.enrollment_id', 'e.id')->whereNotNull('moved.ended_at'));
    }

    private function belowSeventyFive(AcademicYear $year): Builder
    {
        $subjects = "(SELECT CONCAT(COUNT(*), ' subject(s): ', GROUP_CONCAT(CONCAT(g2.subject_name_snapshot, ' ', g2.final_grade) ORDER BY g2.subject_name_snapshot SEPARATOR ', ')) FROM student_subject_grades g2 WHERE g2.enrollment_id = e.id AND g2.status = 'finalized' AND g2.final_grade < 75)";

        return $this->base(DB::table('enrollments as e'), $year, "'below_75'", $subjects, 'NULL')
            ->where('e.academic_year_id', $year->id)->whereIn('e.status', self::ENROLLED)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('student_subject_grades as low')->whereColumn('low.enrollment_id', 'e.id')
                ->where('low.status', 'finalized')->where('low.final_grade', '<', 75));
    }
}
