<?php

namespace App\Modules\Reporting\Analytics;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Support\Facades\DB;

/**
 * D-069 top students per grade level or section (pre-final panel: "reports identify predictive top 10
 * students grade level/section").
 *
 * - "final": Registrar-approved final grades only (D-038). A learner is ranked only when every active subject
 *   of their section has an approved final grade; others are counted as "not yet complete".
 * - "projected": an owner-approved exception to D-038 for Admin and Registrar. Each subject counts its approved
 *   final grade, or else the average of the quarter grades its teacher has submitted for approval. Drafts are
 *   never used. The page labels these as not yet approved.
 *
 * Ties share a rank (1, 2, 2, 4, ...), so a group can list more than ten learners when the tenth place is tied.
 */
class TopStudentsQuery
{
    public const LIMIT = 10;

    /**
     * @return array{groups: list<array{label: string, rows: list<array<string, mixed>>, ranked: int, incomplete: int}>, basis: string, group: string}
     */
    public function for(AcademicYear $year, string $group, string $basis): array
    {
        $statuses = $basis === 'projected' ? ['submitted', 'finalized'] : ['finalized'];

        $rows = DB::table('student_subject_grades as sgr')
            ->join('enrollments as e', 'e.id', '=', 'sgr.enrollment_id')
            ->join('student_profiles as s', 's.id', '=', 'e.student_id')
            ->join('grade_levels as g', 'g.id', '=', 'e.grade_level_id')
            ->join('section_assignments as sa', fn ($join) => $join->on('sa.enrollment_id', '=', 'e.id')->where('sa.status', 'confirmed')->whereNull('sa.ended_at'))
            ->join('sections as sec', 'sec.id', '=', 'sa.section_id')
            ->where('e.academic_year_id', $year->id)
            ->whereNotIn('e.status', [...Enrollment::TERMINAL_STATUSES, 'draft'])
            ->whereIn('sgr.status', $statuses)
            ->get(['e.id as enrollment_id', 's.first_name', 's.last_name', 's.student_number', 'g.id as grade_id', 'g.name as grade', 'g.sequence', 'sec.id as section_id', 'sec.name as section',
                'sgr.status', 'sgr.final_grade', 'sgr.quarter_1', 'sgr.quarter_2', 'sgr.quarter_3', 'sgr.quarter_4']);

        $offerings = DB::table('section_subjects')->where('status', 'active')->groupBy('section_id')
            ->selectRaw('section_id, COUNT(*) as total')->pluck('total', 'section_id');

        $learners = $rows->groupBy('enrollment_id')->map(function ($subjects) use ($basis, $offerings): ?array {
            $values = $subjects->map(function ($row) use ($basis) {
                if ($row->status === 'finalized' && $row->final_grade !== null) {
                    return (float) $row->final_grade;
                }
                if ($basis !== 'projected') {
                    return null;
                }
                $quarters = array_filter([$row->quarter_1, $row->quarter_2, $row->quarter_3, $row->quarter_4], fn ($value) => $value !== null);

                return $quarters === [] ? null : array_sum($quarters) / count($quarters);
            })->filter(fn ($value) => $value !== null);
            if ($values->isEmpty()) {
                return null;
            }
            $first = $subjects->first();
            $required = (int) ($offerings[$first->section_id] ?? 0);

            return [
                'name' => trim($first->first_name.' '.$first->last_name),
                'student_number' => $first->student_number,
                'grade_id' => $first->grade_id, 'grade' => $first->grade, 'sequence' => $first->sequence,
                'section_id' => $first->section_id, 'section' => $first->section,
                'average' => round($values->avg(), 2),
                'subjects' => $values->count(),
                'required' => $required,
                'complete' => $required > 0 && $values->count() >= $required,
            ];
        })->filter()->values();

        $keyOf = fn (array $learner) => $group === 'section' ? $learner['section_id'] : $learner['grade_id'];
        $groups = $learners->groupBy($keyOf)->map(function ($members) use ($group, $basis): array {
            $first = $members->first();
            $eligible = $basis === 'final' ? $members->where('complete', true) : $members;
            $sorted = $eligible->sortBy([['average', 'desc'], ['name', 'asc']])->values();
            $ranked = $sorted->map(fn (array $learner) => $learner + ['rank' => 1 + $sorted->where('average', '>', $learner['average'])->count()])
                ->filter(fn (array $learner) => $learner['rank'] <= self::LIMIT)->values()->all();

            return [
                'label' => $group === 'section' ? $first['grade'].' – '.$first['section'] : $first['grade'],
                'order' => sprintf('%06d-%s', $first['sequence'], $group === 'section' ? $first['section'] : ''),
                'rows' => $ranked,
                'ranked' => $eligible->count(),
                'incomplete' => $members->count() - $eligible->count(),
            ];
        })->sortBy('order')->values()->all();

        return ['groups' => $groups, 'basis' => $basis, 'group' => $group];
    }
}
