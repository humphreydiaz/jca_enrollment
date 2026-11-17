<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Calculations\Money;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\Payment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostPaymentAction
{
    public function __construct(
        private NextFinancialReferenceAction $reference,
        private AuditRecorder $audit,
        private ValidatePaymentBalance $balanceValidator
    ) {}

    public function execute(Payment $payment, User $actor): Payment
    {
        if (! ($actor->hasRole('admin') || $actor->hasRole('accounting'))) {
            throw ValidationException::withMessages([
                'payment' => 'Only Administrator or Accounting may post payments.',
            ]);
        }

        return DB::transaction(function () use ($payment, $actor): Payment {

            $assessment = FeeAssessment::query()
                ->lockForUpdate()
                ->findOrFail($payment->fee_assessment_id);

            $record = Payment::query()
                ->lockForUpdate()
                ->with('allocations')
                ->findOrFail($payment->id);

            if ($record->status !== 'draft') {
                throw ValidationException::withMessages([
                    'payment' => 'Only a draft payment may be posted.',
                ]);
            }

            // Check the current outstanding balance before posting.
            $this->balanceValidator->execute(
                $assessment,
                (string) $record->amount
            );

            // Ensure payment allocations do not exceed the amount.
            $allocated = Money::sum(
                $record->allocations->pluck('amount')
            );

            if (
                Money::cents($allocated) >
                Money::cents((string) $record->amount)
            ) {
                throw ValidationException::withMessages([
                    'allocations' => 'Allocations exceed payment amount.',
                ]);
            }

            // Generate references only after validation succeeds.
            $record->update([
                'payment_reference' => $this->reference->execute('payment', 'PAY'),
                'receipt_reference' => $this->reference->execute('receipt', 'JCA-REC'),
                'status' => 'posted',
                'posted_by' => $actor->id,
                'posted_at' => now(),
            ]);

            $this->audit->record(
                $actor,
                'billing.payment.posted',
                $record
            );

            return $record;
        });
    }
}