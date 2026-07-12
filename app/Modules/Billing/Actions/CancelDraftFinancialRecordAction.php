<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\Payment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelDraftFinancialRecordAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function assessment(FeeAssessment $assessment, string $reason, User $actor): FeeAssessment
    {
        return DB::transaction(function () use ($assessment, $reason, $actor): FeeAssessment {
            $assessment = FeeAssessment::query()->lockForUpdate()->findOrFail($assessment->id);
            if ($assessment->status !== 'draft') {
                throw ValidationException::withMessages(['assessment' => 'Only a draft assessment may be cancelled.']);
            }
            $assessment->update(['status' => 'cancelled', 'revision_reason' => $reason]);
            $this->audit->record($actor, 'billing.assessment.draft_cancelled', $assessment);

            return $assessment;
        });
    }

    public function payment(Payment $payment, string $reason, User $actor): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $actor): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== 'draft') {
                throw ValidationException::withMessages(['payment' => 'Only a draft payment may be cancelled.']);
            }
            $payment->update(['status' => 'cancelled', 'cancellation_reason' => $reason, 'cancelled_by' => $actor->id, 'cancelled_at' => now()]);
            $this->audit->record($actor, 'billing.payment.draft_cancelled', $payment);

            return $payment;
        });
    }
}
