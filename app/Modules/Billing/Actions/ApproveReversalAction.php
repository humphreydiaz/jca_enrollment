<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Calculations\Money;
use App\Modules\Billing\Models\PaymentReversal;
use App\Modules\Billing\Models\PaymentReversalAllocation;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveReversalAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(PaymentReversal $reversal, User $actor, bool $approve = true): PaymentReversal
    {
        return DB::transaction(function () use ($reversal, $actor, $approve) {
            $record = PaymentReversal::query()->lockForUpdate()->findOrFail($reversal->id);
            if (! $actor->hasRole('admin') || $record->requested_by === $actor->id) {
                throw ValidationException::withMessages(['approval' => 'An independent Admin must approve a reversal.']);
            }
            if ($record->status !== 'requested') {
                throw ValidationException::withMessages(['reversal' => 'This reversal has already been decided.']);
            }
            $payment = $record->payment()->lockForUpdate()->firstOrFail();
            if (! $approve) {
                $record->update(['status' => 'rejected', 'decided_by' => $actor->id, 'decided_at' => now()]);
                $this->audit->record($actor, 'billing.reversal.rejected', $record);

                return $record;
            }
            $approved = PaymentReversal::query()->where('payment_id', $payment->id)->where('status', 'approved')->sum('amount');
            if (Money::cents((string) $approved) + Money::cents((string) $record->amount) > Money::cents((string) $payment->amount)) {
                throw ValidationException::withMessages(['amount' => 'Reversal exceeds the remaining posted amount.']);
            }
            $remaining = Money::cents((string) $record->amount);
            $totalAllocated = Money::cents(Money::sum($payment->allocations()->pluck('amount')));
            $priorAllocatedReversals = Money::cents((string) PaymentReversalAllocation::query()->whereHas('reversal', fn ($query) => $query->where('payment_id', $payment->id)->where('status', 'approved'))->sum('amount'));
            $priorReversalTotal = Money::cents((string) $approved);
            $unappliedAvailable = max(0, Money::cents((string) $payment->amount) - $totalAllocated - ($priorReversalTotal - $priorAllocatedReversals));
            $remaining -= min($remaining, $unappliedAvailable);
            foreach ($payment->allocations()->orderBy('id')->lockForUpdate()->get() as $allocation) {
                if ($remaining === 0) {
                    break;
                }
                $alreadyReversed = PaymentReversalAllocation::query()->where('payment_allocation_id', $allocation->id)->sum('amount');
                $available = Money::cents((string) $allocation->amount) - Money::cents((string) $alreadyReversed);
                $amount = min($remaining, max(0, $available));
                if ($amount > 0) {
                    PaymentReversalAllocation::query()->create(['payment_reversal_id' => $record->id, 'payment_allocation_id' => $allocation->id, 'amount' => Money::decimal($amount)]);
                    $remaining -= $amount;
                }
            }
            $record->update(['status' => 'approved', 'decided_by' => $actor->id, 'decided_at' => now()]);
            $this->audit->record($actor, 'billing.reversal.approved', $record);

            return $record;
        });
    }
}
