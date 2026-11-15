<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Models\AssessmentItem;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\Payment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateDraftPaymentAction
{
    public function __construct(
        private AuditRecorder $audit,
        private AllocateDraftPaymentAction $allocate,
        private ValidatePaymentBalance $balanceValidator
    ) {}

    public function execute(
        FeeAssessment $assessment,
        array $data,
        User $actor
    ): Payment {
        return DB::transaction(function () use ($assessment, $data, $actor): Payment {
            $assessment = FeeAssessment::query()
                ->lockForUpdate()
                ->findOrFail($assessment->id);

            if ($assessment->status !== 'finalized') {
                throw ValidationException::withMessages([
                    'assessment' => 'Payments require the current finalized assessment.',
                ]);
            }

            $this->balanceValidator->execute(
                $assessment,
                (string) $data['amount']
            );

            $itemId = $data['assessment_item_id'] ?? null;

            if (
                $itemId &&
                ! AssessmentItem::query()
                    ->whereKey($itemId)
                    ->where('fee_assessment_id', $assessment->id)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'assessment_item_id' => 'The allocation target does not belong to this assessment.',
                ]);
            }

            $payment = Payment::query()->create([
                ...collect($data)->except('assessment_item_id')->all(),
                'fee_assessment_id' => $assessment->id,
                'status' => 'draft',
                'created_by' => $actor->id,
            ]);

            $this->allocate->execute($payment, $itemId);

            $this->audit->record(
                $actor,
                'billing.payment.draft_created',
                $payment,
                ['method' => $payment->method]
            );

            return $payment;
        });
    }
}