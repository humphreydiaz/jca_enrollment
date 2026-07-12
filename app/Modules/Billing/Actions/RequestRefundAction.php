<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Calculations\BalanceCalculator;
use App\Modules\Billing\Calculations\Money;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\Refund;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RequestRefundAction
{
    public function __construct(private BalanceCalculator $balance, private AuditRecorder $audit) {}

    public function execute(FeeAssessment $assessment, string $amount, string $reason, User $actor): Refund
    {
        return DB::transaction(function () use ($assessment, $amount, $reason, $actor): Refund {
            $assessment = FeeAssessment::query()->lockForUpdate()->findOrFail($assessment->id);
            if (Money::cents($amount) > Money::cents($this->balance->calculate($assessment)['credit'])) {
                throw ValidationException::withMessages(['amount' => 'Refund cannot exceed the available credit.']);
            }
            $refund = Refund::query()->create(['fee_assessment_id' => $assessment->id, 'amount' => $amount, 'reason' => $reason, 'status' => 'requested', 'requested_by' => $actor->id]);
            $this->audit->record($actor, 'billing.refund.requested', $refund);

            return $refund;
        });
    }
}
