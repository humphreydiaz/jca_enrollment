<?php

namespace App\Modules\Grading\Queries;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Academics\Queries\TeacherWorkspaceQuery;
use App\Modules\Academics\Support\CurrentAcademicYear;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Grading\Support\NumericGradeLevels;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use Illuminate\Support\Collection;

/**
 * The teacher's Grades page: their Grades 1-10 subject classes for one school year,
 * grouped by section, each with how far grading has progressed for its current roster.
 */
class TeacherGradebookQuery
{
    public const STAGES = ['approved', 'submitted', 'ready', 'in_progress', 'not_started'];

    public function __construct(private readonly CurrentAcademicYear $currentYear) {}

    /**
     * @return array{years: Collection<int, AcademicYear>, year: ?AcademicYear, sections: Collection<int, array<string, mixed>>, totals: array<string, int>}
     */
    public function forUser(User $user, ?string $yearPublicId): array
    {
        $assignments = TeachingAssignment::query()->active()->forUser($user)
            ->where('assignment_type', 'subject_teacher')->whereNotNull('section_subject_id')
            ->whereHas('sectionSubject', fn ($query) => $query->whereColumn('section_subjects.section_id', 'teaching_assignments.section_id'))
            ->whereHas('section.gradeLevel', fn ($query) => $query->whereIn('code', NumericGradeLevels::codes()))
            ->with(['section.academicYear', 'section.gradeLevel', 'sectionSubject.subject'])
            ->get();

        $years = TeacherWorkspaceQuery::yearsOf($assignments);
        $year = $this->currentYear->resolve($years, $yearPublicId);
        $inYear = $year ? $assignments->filter(fn (TeachingAssignment $assignment): bool => $assignment->section->academic_year_id === $year->id)->values() : collect();

        $rosters = SectionAssignment::query()
            ->whereIn('section_id', $inYear->pluck('section_id')->unique())
            ->where('status', 'confirmed')->whereNull('ended_at')
            ->whereHas('enrollment', fn ($query) => $query->where('status', 'enrolled'))
            ->get(['section_id', 'enrollment_id'])
            ->groupBy('section_id')
            ->map(fn (Collection $rows) => $rows->pluck('enrollment_id')->unique()->values());

        $grades = StudentSubjectGrade::query()
            ->whereIn('enrollment_id', $rosters->flatten()->unique())
            ->whereIn('subject_id', $inYear->map(fn (TeachingAssignment $assignment) => $assignment->sectionSubject->subject_id)->unique())
            ->get(['enrollment_id', 'subject_id', 'status', 'quarter_1', 'quarter_2', 'quarter_3', 'quarter_4']);

        $sections = $inYear->groupBy('section_id')->map(function (Collection $classes) use ($rosters, $grades): array {
            $section = $classes->first()->section;
            $roster = $rosters->get($section->id, collect());

            return [
                'section' => $section,
                'studentCount' => $roster->count(),
                'classes' => $classes
                    ->sortBy(fn (TeachingAssignment $assignment): string => $assignment->sectionSubject->subject->name)
                    ->values()
                    ->map(fn (TeachingAssignment $assignment): array => [
                        'assignment' => $assignment,
                        'progress' => $this->progress($roster, $grades->where('subject_id', $assignment->sectionSubject->subject_id)->keyBy('enrollment_id')),
                    ]),
            ];
        })->sortBy(fn (array $entry): string => sprintf('%05d-%05d-%s', $entry['section']->gradeLevel->sequence, $entry['section']->sort_order ?? 0, $entry['section']->name))->values();

        $classes = $sections->flatMap(fn (array $entry) => $entry['classes']);

        return [
            'years' => $years,
            'year' => $year,
            'sections' => $sections,
            'totals' => [
                'classes' => $classes->count(),
                'toFinish' => $classes->sum(fn (array $class): int => $class['progress']['in_progress'] + $class['progress']['not_started']),
                'toSubmit' => $classes->sum(fn (array $class): int => $class['progress']['ready']),
                'waiting' => $classes->sum(fn (array $class): int => $class['progress']['submitted']),
            ],
        ];
    }

    /**
     * @param  Collection<int, int>  $roster
     * @param  Collection<int, StudentSubjectGrade>  $grades  keyed by enrollment id
     * @return array<string, int>
     */
    private function progress(Collection $roster, Collection $grades): array
    {
        $counts = array_fill_keys(self::STAGES, 0);
        foreach ($roster as $enrollmentId) {
            $counts[self::stage($grades->get($enrollmentId))]++;
        }
        $counts['total'] = $roster->count();

        return $counts;
    }

    public static function stage(?StudentSubjectGrade $grade): string
    {
        if ($grade === null) {
            return 'not_started';
        }

        return match ($grade->status) {
            'finalized' => 'approved',
            'submitted' => 'submitted',
            default => $grade->isComplete() ? 'ready'
                : (collect([$grade->quarter_1, $grade->quarter_2, $grade->quarter_3, $grade->quarter_4])->filter(fn ($value) => $value !== null)->isEmpty() ? 'not_started' : 'in_progress'),
        };
    }
}
