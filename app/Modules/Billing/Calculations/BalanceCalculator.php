<?php

namespace App\Modules\Billing\Calculations;

use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\PaymentReversal;
use Illuminate\Support\Facades\DB;

class BalanceCalculator
{
    public function calculate(FeeAssessment $assessment): array
    {
        $posted = $assessment->payments()->where('status', 'posted')->sum('amount');
        $paymentIds = $assessment->payments()->where('status', 'posted')->pluck('id');
        $reversed = PaymentReversal::query()->whereIn('payment_id', $paymentIds)->where('status', 'approved')->sum('amount');
        $adjustments = $assessment->adjustments()->where('status', 'approved')->get();
        $debits = Money::sum($adjustments->where('adjustment_type', 'debit')->pluck('amount'));
        $reductions = Money::sum($adjustments->whereIn('adjustment_type', ['credit', 'waiver'])->pluck('amount'));
        $refunds = (string) DB::table('refunds')->where('fee_assessment_id', $assessment->id)->where('status', 'approved')->sum('amount');
        $outgoingCredit = (string) DB::table('credit_allocations')->join('payments', 'payments.id', '=', 'credit_allocations.source_payment_id')->where('payments.fee_assessment_id', $assessment->id)->sum('credit_allocations.amount');
        $incomingCredit = (string) DB::table('credit_allocations')->join('assessment_items', 'assessment_items.id', '=', 'credit_allocations.target_assessment_item_id')->where('assessment_items.fee_assessment_id', $assessment->id)->sum('credit_allocations.amount');
        $raw = Money::cents((string) $assessment->net_total) + Money::cents($debits) - Money::cents($reductions) - Money::cents((string) $posted) + Money::cents((string) $reversed) + Money::cents($refunds) + Money::cents($outgoingCredit) - Money::cents($incomingCredit);

        return ['raw' => Money::decimal($raw), 'due' => Money::decimal(max(0, $raw)), 'credit' => Money::decimal(max(0, -$raw)), 'paid' => Money::decimal(Money::cents((string) $posted) - Money::cents((string) $reversed) - Money::cents($refunds) - Money::cents($outgoingCredit) + Money::cents($incomingCredit))];
    }
}
