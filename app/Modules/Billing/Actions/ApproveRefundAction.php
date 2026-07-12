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

final class ApproveRefundAction
{
    public function __construct(private AuditRecorder $audit, private BalanceCalculator $balance) {}

    public function execute(Refund $refund, User $admin, bool $approve, ?string $releaseReference = null, ?string $releasedOn = null, ?string $decisionReason = null): Refund
    {
        return DB::transaction(function () use ($refund, $admin, $approve, $releaseReference, $releasedOn, $decisionReason): Refund {
            $record = Refund::query()->lockForUpdate()->findOrFail($refund->id);
            if (! $admin->hasRole('admin') || $record->requested_by === $admin->id || $record->status !== 'requested') {
                throw ValidationException::withMessages(['approval' => 'An independent Admin must decide a pending refund.']);
            }
            $assessment = FeeAssessment::query()->lockForUpdate()->findOrFail($record->fee_assessment_id);
            if ($approve && Money::cents((string) $record->amount) > Money::cents($this->balance->calculate($assessment)['credit'])) {
                throw ValidationException::withMessages(['amount' => 'Available credit no longer supports this refund.']);
            }
            $record->update(['status' => $approve ? 'approved' : 'rejected', 'approved_by' => $approve ? $admin->id : null, 'decided_by' => $admin->id, 'decided_at' => now(), 'decision_reason' => $decisionReason, 'release_reference' => $approve ? $releaseReference : null, 'released_on' => $approve ? $releasedOn : null]);
            $this->audit->record($admin, $approve ? 'billing.refund.approved' : 'billing.refund.rejected', $record);

            return $record;
        });
    }
}
