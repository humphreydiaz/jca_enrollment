<?php

namespace App\Modules\Requirements\Actions;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Requirements\Models\RequirementRule;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Removes the requirement rules generated for a school year that was created by mistake.
 * Called only by the Academics deletion workflow after it has proven the year has no enrollments.
 */
class RemoveUnusedYearRequirementRulesAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(AcademicYear $year, User $actor): int
    {
        return DB::transaction(function () use ($year, $actor): int {
            if (DB::table('enrollments')->where('academic_year_id', $year->id)->exists()) {
                throw ValidationException::withMessages(['academic_year' => 'This school year has enrollments, so its requirement rules must be kept.']);
            }
            $removed = RequirementRule::query()->where('academic_year_id', $year->id)->delete();
            if ($removed > 0) {
                $this->audit->record($actor, 'requirements.rules.removed_with_unused_year', $year, ['rules' => $removed]);
            }

            return $removed;
        });
    }
}
