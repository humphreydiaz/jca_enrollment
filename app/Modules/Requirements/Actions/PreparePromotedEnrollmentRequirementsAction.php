<?php

namespace App\Modules\Requirements\Actions;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PreparePromotedEnrollmentRequirementsAction
{
    public function __construct(
        private readonly GenerateEnrollmentChecklistAction $checklist,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(
        Enrollment $source,
        Enrollment $target,
        ?User $actor
    ): int {
        return DB::transaction(function () use ($source, $target, $actor): int {
            $lockedSource = Enrollment::query()
                ->lockForUpdate()
                ->findOrFail($source->id);

            $lockedTarget = Enrollment::query()
                ->lockForUpdate()
                ->findOrFail($target->id);

            if (
                $lockedTarget->previous_enrollment_id !== $lockedSource->id ||
                $lockedTarget->student_id !== $lockedSource->student_id
            ) {
                throw ValidationException::withMessages([
                    'requirements' =>
                        'Requirements may only be carried to the linked promotion enrollment of the same student.',
                ]);
            }

            // Generate the hard-copy checklist for the promoted enrollment.
            $this->checklist->execute($lockedTarget);

            // Only physically received and accepted requirements qualify.
            // Historical soft-copy verification is not admission evidence.
            $accepted = EnrollmentRequirement::query()
                ->with('documentType')
                ->where('enrollment_id', $lockedSource->id)
                ->whereNotNull('received_at')
                ->whereIn('status', ['submitted', 'verified'])
                ->lockForUpdate()
                ->get();

            $carried = 0;

            foreach ($accepted as $sourceRequirement) {
                $targetRequirement = EnrollmentRequirement::query()
                    ->where('enrollment_id', $lockedTarget->id)
                    ->where(
                        'document_type_id',
                        $sourceRequirement->document_type_id
                    )
                    ->lockForUpdate()
                    ->first();

                // Preserve the target's own recorded physical receipt
                // or existing review decision.
                if (
                    $targetRequirement !== null &&
                    $this->hasOwnEvidence($targetRequirement)
                ) {
                    continue;
                }

                $targetRequirement ??= new EnrollmentRequirement([
                    'enrollment_id' => $lockedTarget->id,
                    'document_type_id' => $sourceRequirement->document_type_id,
                    'rule_snapshot' => $sourceRequirement->rule_snapshot,
                ]);

                $targetRequirement->fill([
                    'carried_from_requirement_id' => $sourceRequirement->id,
                    'carried_over_by' => $actor?->id,
                    'carried_over_at' => now(),

                    'status' => $sourceRequirement->status,

                    'received_by' => $sourceRequirement->received_by,
                    'received_at' => $sourceRequirement->received_at,
                    'receipt_notes' => $sourceRequirement->receipt_notes,

                    'reviewed_by' => $sourceRequirement->reviewed_by,
                    'reviewed_at' => $sourceRequirement->reviewed_at,
                    'review_reason' => $sourceRequirement->review_reason,

                    'waived_by' => null,
                ]);

                $targetRequirement->save();

                $this->audit->record(
                    $actor,
                    'requirements.hard_copy.carried_forward',
                    $targetRequirement,
                    [
                        'source_enrollment_public_id' =>
                            $lockedSource->public_id,
                        'target_enrollment_public_id' =>
                            $lockedTarget->public_id,
                        'document_type_code' =>
                            $sourceRequirement->documentType->code,
                    ]
                );

                $carried++;
            }

            return $carried;
        });
    }

    /**
     * Do not overwrite physical receipts or existing review decisions
     * in the promoted enrollment.
     */
    private function hasOwnEvidence(
        EnrollmentRequirement $requirement
    ): bool {
        return in_array(
            $requirement->status,
            ['verified', 'waived', 'rejected'],
            true
        ) || $requirement->received_at !== null;
    }
}
