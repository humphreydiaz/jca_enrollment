<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizeAssessmentAction
{
    public function __construct(
        private AuditRecorder $audit
    ) {}

    public function execute(
        FeeAssessment $assessment,
        User $actor
    ): FeeAssessment {
        return DB::transaction(function () use ($assessment, $actor) {

            // Lock enrollment to serialize assessment operations.
            $enrollment = Enrollment::query()
                ->lockForUpdate()
                ->findOrFail($assessment->enrollment_id);

            // Lock the assessment being finalized.
            $record = FeeAssessment::query()
                ->lockForUpdate()
                ->findOrFail($assessment->id);

            if ($record->enrollment_id !== $enrollment->id) {
                throw ValidationException::withMessages([
                    'assessment' => 'Assessment enrollment mismatch.',
                ]);
            }

            if ($record->status !== 'draft') {
                throw ValidationException::withMessages([
                    'assessment' => 'Only a draft assessment may be finalized.',
                ]);
            }

            // Prevent replacement of assessments with posted payments.
            $hasPostedPayment = FeeAssessment::query()
                ->where('enrollment_id', $enrollment->id)
                ->whereHas('payments', function ($query) {
                    $query->where('status', 'posted');
                })
                ->exists();

            if ($hasPostedPayment) {
                throw ValidationException::withMessages([
                    'assessment' =>
                        'A posted payment exists. Use an approved financial adjustment instead of replacing the finalized assessment.',
                ]);
            }

            // Supersede previous finalized assessment.
            FeeAssessment::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('status', 'finalized')
                ->update([
                    'status' => 'superseded',
                    'current_finalized_key' => null,
                ]);

            // Finalize the new assessment.
            $record->update([
                'status' => 'finalized',
                'current_finalized_key' => (string) $enrollment->id,
                'finalized_by' => $actor->id,
                'finalized_at' => now(),
            ]);

            $this->audit->record(
                $actor,
                'billing.assessment.finalized',
                $record
            );

            return $record;
        });
    }
}
