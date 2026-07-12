<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Calculations\BalanceCalculator;
use App\Modules\Billing\Calculations\Money;
use App\Modules\Billing\Models\AssessmentItem;
use App\Modules\Billing\Models\CreditAllocation;
use App\Modules\Billing\Models\Payment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AllocateStudentCreditAction
{
    public function __construct(private BalanceCalculator $balance, private AuditRecorder $audit) {}

    public function execute(Payment $sourcePayment, AssessmentItem $targetItem, string $amount, string $reason, User $actor): CreditAllocation
    {
        return DB::transaction(function () use ($sourcePayment, $targetItem, $amount, $reason, $actor): CreditAllocation {
            $payment = Payment::query()->with('assessment.enrollment')->lockForUpdate()->findOrFail($sourcePayment->id);
            $item = AssessmentItem::query()->with('assessment.enrollment')->lockForUpdate()->findOrFail($targetItem->id);
            if ($payment->status !== 'posted' || $item->assessment->status !== 'finalized') {
                throw ValidationException::withMessages(['credit' => 'Credit may move only from a posted payment to a finalized assessment.']);
            }
            $sourceEnrollment = $payment->assessment->enrollment;
            $targetEnrollment = $item->assessment->enrollment;
            if ($sourceEnrollment->student_id !== $targetEnrollment->student_id || $sourceEnrollment->academic_year_id !== $targetEnrollment->academic_year_id) {
                throw ValidationException::withMessages(['credit' => 'Credit cannot move to another student or school year.']);
            }
            if (Money::cents($amount) <= 0 || Money::cents($amount) > Money::cents($this->balance->calculate($payment->assessment)['credit'])) {
                throw ValidationException::withMessages(['amount' => 'Credit allocation exceeds available student credit.']);
            }
            $allocation = CreditAllocation::query()->create(['source_payment_id' => $payment->id, 'target_assessment_item_id' => $item->id, 'amount' => $amount, 'reason' => $reason, 'allocated_by' => $actor->id, 'allocated_at' => now()]);
            $this->audit->record($actor, 'billing.credit.allocated', $allocation);

            return $allocation;
        });
    }
}
