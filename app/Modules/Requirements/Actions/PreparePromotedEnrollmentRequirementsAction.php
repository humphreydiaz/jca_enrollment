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

    public function execute(Enrollment $source, Enrollment $target, ?User $actor): int
    {
        return DB::transaction(function () use ($source, $target, $actor): int {
            $lockedSource = Enrollment::query()->lockForUpdate()->findOrFail($source->id);
            $lockedTarget = Enrollment::query()->lockForUpdate()->findOrFail($target->id);

            if ($lockedTarget->previous_enrollment_id !== $lockedSource->id || $lockedTarget->student_id !== $lockedSource->student_id) {
                throw ValidationException::withMessages([
                    'requirements' => 'Requirement evidence can be carried only to the linked promotion enrollment for the same student.',
                ]);
            }

            $this->checklist->execute($lockedTarget);

            $accepted = EnrollmentRequirement::query()
                ->with('documentType')
                ->where('enrollment_id', $lockedSource->id)
                ->where(function ($query): void {
                    $query->where('status', 'verified')
                        ->orWhere(function ($received): void {
                            $received->where('status', 'submitted')->whereNotNull('received_at');
                        })
                        // A verified soft copy is durable evidence too. A birth
                        // certificate or Form 138 is collected once, not again
                        // every school year, so its accepted state follows the
                        // learner into the promoted enrollment.
                        ->orWhere('soft_copy_status', 'verified');
                })
                ->lockForUpdate()
                ->get();

            $carried = 0;

            foreach ($accepted as $sourceRequirement) {
                $targetRequirement = EnrollmentRequirement::query()
                    ->where('enrollment_id', $lockedTarget->id)
                    ->where('document_type_id', $sourceRequirement->document_type_id)
                    ->lockForUpdate()
                    ->first();

                if ($targetRequirement !== null && $this->hasOwnEvidence($targetRequirement)) {
                    continue;
                }

                $targetRequirement ??= new EnrollmentRequirement([
                    'enrollment_id' => $lockedTarget->id,
                    'document_type_id' => $sourceRequirement->document_type_id,
                    'rule_snapshot' => $sourceRequirement->rule_snapshot,
                ]);

                $attributes = [
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
                ];

                // Only an accepted soft copy travels; a rejected or missing one
                // would just carry noise into the new school year.
                $softCopyCarried = $sourceRequirement->soft_copy_status === 'verified';
                if ($softCopyCarried) {
                    $attributes += [
                        'soft_copy_status' => $sourceRequirement->soft_copy_status,
                        'soft_copy_reviewed_by' => $sourceRequirement->soft_copy_reviewed_by,
                        'soft_copy_reviewed_at' => $sourceRequirement->soft_copy_reviewed_at,
                        'soft_copy_review_reason' => $sourceRequirement->soft_copy_review_reason,
                    ];
                }

                $targetRequirement->fill($attributes);
                $targetRequirement->save();

                $this->audit->record($actor, 'requirements.hard_copy.carried_forward', $targetRequirement, [
                    'source_enrollment_public_id' => $lockedSource->public_id,
                    'target_enrollment_public_id' => $lockedTarget->public_id,
                    'document_type_code' => $sourceRequirement->documentType->code,
                    'soft_copy_carried' => $softCopyCarried,
                ]);
                $carried++;
            }

            return $carried;
        });
    }

    /**
     * Evidence the promoted enrollment already holds in its own right. Anything
     * the family has already filed for the new year outranks a carried copy.
     */
    private function hasOwnEvidence(EnrollmentRequirement $requirement): bool
    {
        return in_array($requirement->status, ['verified', 'waived', 'rejected'], true)
            || ($requirement->status === 'submitted' && $requirement->received_at !== null)
            || in_array($requirement->soft_copy_status, ['submitted', 'verified'], true);
    }
}
