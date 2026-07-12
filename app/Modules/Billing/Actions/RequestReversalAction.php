<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentReversal;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

final class RequestReversalAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(Payment $payment, string $amount, string $reason, User $actor): PaymentReversal
    {
        if ($payment->status !== 'posted') {
            throw ValidationException::withMessages(['payment' => 'Only a posted payment may be reversed.']);
        }
        $reversal = PaymentReversal::query()->create(['payment_id' => $payment->id, 'amount' => $amount, 'reason' => $reason, 'requested_by' => $actor->id, 'requested_at' => now()]);
        $this->audit->record($actor, 'billing.reversal.requested', $reversal);

        return $reversal;
    }
}
