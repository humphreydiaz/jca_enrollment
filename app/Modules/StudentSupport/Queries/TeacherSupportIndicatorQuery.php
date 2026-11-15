<?php

namespace App\Modules\StudentSupport\Queries;

use App\Modules\StudentSupport\Models\ApprovedSupportSummary;
use Illuminate\Support\Collection;

/**
 * Tells a teacher's roster which students have support notes to read, without loading
 * or logging any summary text. Callers pass only enrollments already scoped to the
 * teacher's current sections; opening a summary still goes through
 * ViewTeacherSafeSummaryAction, which re-checks access and writes the access log.
 */
class TeacherSupportIndicatorQuery
{
    /**
     * @param  Collection<int, int>  $enrollmentIds
     * @return Collection<int, int> enrollment ids with a currently effective approved summary
     */
    public function enrollmentsWithSummary(Collection $enrollmentIds): Collection
    {
        if ($enrollmentIds->isEmpty()) {
            return collect();
        }

        return ApprovedSupportSummary::query()
            ->currentlyEffective()
            ->join('support_cases', 'support_cases.id', '=', 'approved_support_summaries.support_case_id')
            ->whereIn('support_cases.enrollment_id', $enrollmentIds->unique()->values())
            ->distinct()
            ->pluck('support_cases.enrollment_id')
            ->map(fn ($id): int => (int) $id);
    }
}
