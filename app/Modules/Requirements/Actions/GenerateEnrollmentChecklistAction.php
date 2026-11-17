<?php

namespace App\Modules\Requirements\Actions;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\Requirements\Models\RequirementRule;

class GenerateEnrollmentChecklistAction
{
    /**
     * Generate the physical-document checklist for an enrollment.
     *
     * Existing requirement records, receipt dates, and review
     * decisions are preserved.
     */
    public function execute(Enrollment $enrollment): int
    {
        $rules = RequirementRule::query()
            ->with('documentType')
            ->where('academic_year_id', $enrollment->academic_year_id)
            ->where('student_category', $enrollment->student_category)
            ->where(function ($query): void {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', now('Asia/Manila')->toDateString());
            })
            ->where(function ($query): void {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', now('Asia/Manila')->toDateString());
            })
            ->get();

        $created = 0;

        foreach ($rules as $rule) {
            $requirement = EnrollmentRequirement::query()
                ->firstOrCreate(
                    [
                        'enrollment_id' => $enrollment->id,
                        'document_type_id' => $rule->document_type_id,
                    ],
                    [
                        'rule_snapshot' => [
                            'is_required' => (bool) $rule->is_required,
                            'is_blocking' => (bool) $rule->is_blocking,
                            'name' => $rule->documentType->name,
                            'notes' => $rule->notes,
                        ],
                        'status' => 'missing',
                    ]
                );

            if ($requirement->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }
}
