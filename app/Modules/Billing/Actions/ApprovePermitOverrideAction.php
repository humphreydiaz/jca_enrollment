<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Models\ExamPermitOverride;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApprovePermitOverrideAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(ExamPermitOverride $override, User $admin, bool $approve, ?string $decisionReason = null): ExamPermitOverride
    {
        return DB::transaction(function () use ($override, $admin, $approve, $decisionReason): ExamPermitOverride {
            if (! $admin->hasRole('admin') || $override->requested_by === $admin->id) {
                throw ValidationException::withMessages(['approval' => 'An independent Admin approval is required.']);
            }
            $record = ExamPermitOverride::query()->lockForUpdate()->findOrFail($override->id);
            if ($record->status !== 'requested' || blank($record->confidential_reason)) {
                throw ValidationException::withMessages(['override' => 'A pending override with a reason is required.']);
            }
            $record->update(['status' => $approve ? 'approved' : 'rejected', 'approved_by' => $approve ? $admin->id : null, 'approved_at' => $approve ? now() : null, 'decided_by' => $admin->id, 'decided_at' => now(), 'decision_reason' => $decisionReason]);
            $this->audit->record($admin, $approve ? 'billing.permit_override.approved' : 'billing.permit_override.rejected', $record, ['reason_category' => $record->reason_category]);

            return $record;
        });
    }
}
