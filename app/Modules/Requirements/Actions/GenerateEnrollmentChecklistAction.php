<?php

namespace App\Modules\Requirements\Actions;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\Requirements\Models\RequirementRule;

class GenerateEnrollmentChecklistAction
{
    public function execute(Enrollment $enrollment): void
    {
        RequirementRule::query()->with('documentType')->where('academic_year_id', $enrollment->academic_year_id)->where('student_category', $enrollment->student_category)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()->toDateString()))
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', now()->toDateString()))
            ->get()->each(function (RequirementRule $rule) use ($enrollment): void {
                EnrollmentRequirement::query()->firstOrCreate(['enrollment_id' => $enrollment->id, 'document_type_id' => $rule->document_type_id], ['rule_snapshot' => ['is_required' => $rule->is_required, 'is_blocking' => $rule->is_blocking, 'name' => $rule->documentType->name, 'notes' => $rule->notes], 'status' => 'missing']);
            });
    }
}
