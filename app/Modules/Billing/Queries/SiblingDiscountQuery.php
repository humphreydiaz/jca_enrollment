<?php

namespace App\Modules\Billing\Queries;

use App\Modules\Billing\Models\SiblingDiscountConfirmation;
use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * D-067: who counts as a sibling for the discount. Two learners are siblings when the same parent/guardian
 * has a verified, current link to both, and both have a current (non-terminal) enrollment in the same
 * school year. Confirmation stays a Registrar decision; this only lists the candidates.
 */
class SiblingDiscountQuery
{
    /**
     * @param  Collection<int, Enrollment>  $enrollments  current enrollments to look up
     * @return array<int, array{siblings: list<string>, confirmation: ?SiblingDiscountConfirmation}> keyed by enrollment id
     */
    public function forEnrollments(Collection $enrollments): array
    {
        $enrollments = $enrollments->filter()->values();
        if ($enrollments->isEmpty()) {
            return [];
        }

        $rows = DB::table('enrollments as mine')
            ->join('guardian_student as my_link', fn ($join) => $join->on('my_link.student_id', '=', 'mine.student_id')->whereNotNull('my_link.verified_at')->whereNull('my_link.ended_at'))
            ->join('guardian_student as their_link', fn ($join) => $join->on('their_link.guardian_id', '=', 'my_link.guardian_id')->on('their_link.student_id', '!=', 'mine.student_id')->whereNotNull('their_link.verified_at')->whereNull('their_link.ended_at'))
            ->join('enrollments as theirs', fn ($join) => $join->on('theirs.student_id', '=', 'their_link.student_id')->on('theirs.academic_year_id', '=', 'mine.academic_year_id'))
            ->join('student_profiles as sibling', 'sibling.id', '=', 'theirs.student_id')
            ->join('grade_levels as g', 'g.id', '=', 'theirs.grade_level_id')
            ->whereIn('mine.id', $enrollments->pluck('id'))
            ->whereNotIn('theirs.status', Enrollment::TERMINAL_STATUSES)
            ->where('theirs.status', '!=', 'draft')
            ->select(['mine.id as enrollment_id', 'sibling.first_name', 'sibling.last_name', 'g.name as grade'])
            ->distinct()
            ->orderBy('sibling.last_name')
            ->get()
            ->groupBy('enrollment_id');

        $confirmations = SiblingDiscountConfirmation::query()->active()->with('confirmer')
            ->whereIn('enrollment_id', $enrollments->pluck('id'))->get()->keyBy('enrollment_id');

        return $enrollments->mapWithKeys(fn (Enrollment $enrollment) => [$enrollment->id => [
            'siblings' => collect($rows[$enrollment->id] ?? [])->map(fn ($row) => trim($row->first_name.' '.$row->last_name).' ('.$row->grade.')')->unique()->values()->all(),
            'confirmation' => $confirmations[$enrollment->id] ?? null,
        ]])->all();
    }
}
