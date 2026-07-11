<?php

namespace App\Modules\Requirements\Actions;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\Requirements\Models\RequirementRule;

class GenerateEnrollmentChecklistAction
{
    public function execute(Enrollment $enrollment): void
    {
        RequirementRule::query()->with('documentType')->where('academic_year_id', $enrollment->academic_year_id)->where('student_category', $enrollment->student_category)->get()->each(function (RequirementRule $rule) use ($enrollment): void {
            EnrollmentRequirement::query()->firstOrCreate(['enrollment_id' => $enrollment->id, 'document_type_id' => $rule->document_type_id], ['rule_snapshot' => ['is_required' => $rule->is_required, 'is_blocking' => $rule->is_blocking, 'name' => $rule->documentType->name, 'notes' => $rule->notes], 'status' => 'missing']);
        });
    }
}
