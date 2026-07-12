<?php

namespace App\Modules\Billing\Calculations;

use App\Modules\Billing\Models\ExamPeriod;
use App\Modules\Billing\Models\FeeAssessment;
use Illuminate\Support\Facades\DB;

final class PermitEligibilityCalculator
{
    /** @return array{required:string,satisfied:string,eligible:bool} */
    public function calculate(FeeAssessment $assessment, ExamPeriod $period): array
    {
        $required = $assessment->plan?->scheduleItems()
            ->whereDate('due_on', '<=', $period->eligibility_cutoff_on)
            ->sum('permit_critical_amount') ?? '0.00';

        $criticalItemIds = $assessment->items()->where('is_permit_critical', true)->pluck('id');
        $posted = (string) DB::table('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('payments.fee_assessment_id', $assessment->id)
            ->where('payments.status', 'posted')
            ->whereIn('payment_allocations.assessment_item_id', $criticalItemIds)
            ->where('payments.posted_at', '<=', now())
            ->sum('payment_allocations.amount');
        $incomingCredit = (string) DB::table('credit_allocations')
            ->whereIn('target_assessment_item_id', $criticalItemIds)
            ->where('allocated_at', '<=', now())
            ->sum('amount');
        $reversed = (string) DB::table('payment_reversal_allocations')
            ->join('payment_reversals', 'payment_reversals.id', '=', 'payment_reversal_allocations.payment_reversal_id')
            ->join('payment_allocations', 'payment_allocations.id', '=', 'payment_reversal_allocations.payment_allocation_id')
            ->where('payment_reversals.status', 'approved')
            ->whereIn('payment_allocations.assessment_item_id', $criticalItemIds)
            ->sum('payment_reversal_allocations.amount');
        $criticalReductions = (string) $assessment->adjustments()->where('status', 'approved')
            ->whereIn('adjustment_type', ['credit', 'waiver'])
            ->whereHas('assessmentItem', fn ($query) => $query->where('is_permit_critical', true))
            ->sum('amount');
        $satisfiedCents = max(0, Money::cents($posted) + Money::cents($incomingCredit) - Money::cents($reversed));
        $requiredCents = max(0, Money::cents((string) $required) - Money::cents($criticalReductions));

        return ['required' => Money::decimal($requiredCents), 'satisfied' => Money::decimal($satisfiedCents), 'eligible' => $satisfiedCents >= $requiredCents];
    }
}
