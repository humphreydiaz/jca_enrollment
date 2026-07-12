<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\PaymentToFollowApproval;
use App\Modules\Shared\Audit\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApprovePaymentToFollowAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(FeeAssessment $assessment, string $approvedUntil, ?string $reason, User $actor): PaymentToFollowApproval
    {
        return DB::transaction(function () use ($assessment, $approvedUntil, $reason, $actor): PaymentToFollowApproval {
            $locked = FeeAssessment::query()->with('enrollment')->lockForUpdate()->findOrFail($assessment->id);
            if (! $locked->plan()->exists()) {
                throw ValidationException::withMessages(['payment_plan' => 'Select a payment plan first.']);
            }
            $baseline = CarbonImmutable::parse($locked->enrollment->approved_at ?? $locked->enrollment->updated_at)->startOfDay();
            $until = CarbonImmutable::parse($approvedUntil)->startOfDay();
            $extension = $until->greaterThan($baseline->addDays(15));
            if ($extension && ! $actor->hasRole('admin')) {
                throw ValidationException::withMessages(['approved_until' => 'Extensions beyond 15 days require Admin approval.']);
            }
            if ($extension && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'An Admin extension requires a reason.']);
            }
            if (! $extension && ! $actor->hasAnyRole(['accounting', 'admin'])) {
                throw ValidationException::withMessages(['approved_until' => 'Only Accounting or Admin may approve payment-to-follow.']);
            }
            $approval = PaymentToFollowApproval::query()->create(['fee_assessment_id' => $locked->id, 'approved_until' => $until, 'reason' => $reason, 'requested_by' => $actor->id, 'approved_by' => $actor->id, 'is_extension' => $extension, 'approved_at' => now()]);
            $firstDue = $locked->plan()->firstOrFail()->scheduleItems()->orderBy('installment_number')->lockForUpdate()->first();
            if ($firstDue && $until->greaterThan($firstDue->due_on)) {
                $firstDue->update(['due_on' => $until->toDateString()]);
            }
            $this->audit->record($actor, $extension ? 'billing.payment_to_follow.extended' : 'billing.payment_to_follow.approved', $locked, ['approved_until' => $until->toDateString()]);

            return $approval;
        });
    }
}
