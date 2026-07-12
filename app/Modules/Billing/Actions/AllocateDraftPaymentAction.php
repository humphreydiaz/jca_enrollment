<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Calculations\Money;
use App\Modules\Billing\Models\AssessmentItem;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentAllocation;
use Illuminate\Validation\ValidationException;

final class AllocateDraftPaymentAction
{
    public function execute(Payment $payment, ?int $preferredItemId = null): void
    {
        if ($payment->status !== 'draft') {
            throw ValidationException::withMessages(['payment' => 'Only draft allocations may be changed.']);
        }
        $payment->allocations()->delete();
        $remaining = Money::cents((string) $payment->amount);
        $items = AssessmentItem::query()->where('fee_assessment_id', $payment->fee_assessment_id)
            ->when($preferredItemId, fn ($query) => $query->orderByRaw('id = ? desc', [$preferredItemId]))
            ->orderByDesc('is_permit_critical')->orderBy('id')->get();
        foreach ($items as $item) {
            if ($remaining === 0) {
                break;
            }
            if ($preferredItemId && $item->id !== $preferredItemId) {
                continue;
            }
            $posted = PaymentAllocation::query()->where('assessment_item_id', $item->id)->whereHas('payment', fn ($query) => $query->where('status', 'posted'))->sum('amount');
            $outstanding = max(0, Money::cents((string) $item->net_amount) - Money::cents((string) $posted));
            $amount = min($remaining, $outstanding);
            if ($amount > 0) {
                PaymentAllocation::query()->create(['payment_id' => $payment->id, 'assessment_item_id' => $item->id, 'amount' => Money::decimal($amount)]);
                $remaining -= $amount;
            }
        }
        if ($remaining > 0) {
            PaymentAllocation::query()->create(['payment_id' => $payment->id, 'amount' => Money::decimal($remaining)]);
        }
    }
}
