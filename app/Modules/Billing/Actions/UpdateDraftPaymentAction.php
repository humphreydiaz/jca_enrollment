<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\Payment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateDraftPaymentAction
{
    public function __construct(
        private AllocateDraftPaymentAction $allocate,
        private AuditRecorder $audit,
        private ValidatePaymentBalance $balanceValidator
    ) {}

    public function execute(
        Payment $payment,
        array $data,
        User $actor
    ): Payment {
        return DB::transaction(function () use ($payment, $data, $actor): Payment {
            $assessment = FeeAssessment::query()
                ->lockForUpdate()
                ->findOrFail($payment->fee_assessment_id);

            $record = Payment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($record->status !== 'draft') {
                throw ValidationException::withMessages([
                    'payment' => 'Only a draft payment may be edited.',
                ]);
            }

            $this->balanceValidator->execute(
                $assessment,
                (string) $data['amount']
            );

            $preferred = $data['assessment_item_id'] ?? null;

            if (
                $preferred &&
                ! $assessment->items()
                    ->whereKey($preferred)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'assessment_item_id' => 'The allocation target does not belong to this assessment.',
                ]);
            }

            $record->update(
                collect($data)->except('assessment_item_id')->all()
            );

            $this->allocate->execute($record, $preferred);

            $this->audit->record(
                $actor,
                'billing.payment.draft_updated',
                $record,
                ['method' => $record->method]
            );

            return $record->load('allocations');
        });
    }
}