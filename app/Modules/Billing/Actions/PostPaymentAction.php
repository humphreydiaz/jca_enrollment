<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Calculations\Money;
use App\Modules\Billing\Models\Payment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostPaymentAction
{
    public function __construct(private NextFinancialReferenceAction $reference, private AuditRecorder $audit) {}

    public function execute(Payment $payment, User $actor): Payment
    {
        if (! $actor->hasRole('accounting')) {
            throw ValidationException::withMessages(['payment' => 'Only Accounting may post payments.']);
        }

        return DB::transaction(function () use ($payment, $actor) {
            $record = Payment::query()->lockForUpdate()->with('allocations')->findOrFail($payment->id);
            if ($record->status !== 'draft') {
                throw ValidationException::withMessages(['payment' => 'Only a draft payment may be posted.']);
            }$allocated = Money::sum($record->allocations->pluck('amount'));
            if (Money::cents($allocated) > Money::cents((string) $record->amount)) {
                throw ValidationException::withMessages(['allocations' => 'Allocations exceed payment amount.']);
            }$record->update(['payment_reference' => $this->reference->execute('payment', 'PAY'), 'receipt_reference' => $this->reference->execute('receipt', 'JCA-REC'), 'status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now()]);
            $this->audit->record($actor, 'billing.payment.posted', $record);

            return $record;
        });
    }
}
