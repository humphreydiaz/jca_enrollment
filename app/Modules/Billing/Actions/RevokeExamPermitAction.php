<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Models\ExamPermit;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RevokeExamPermitAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(ExamPermit $permit, string $reason, User $admin): ExamPermit
    {
        return DB::transaction(function () use ($permit, $reason, $admin): ExamPermit {
            if (! $admin->hasRole('admin')) {
                throw ValidationException::withMessages(['permit' => 'Admin authorization is required.']);
            }
            $record = ExamPermit::query()->lockForUpdate()->findOrFail($permit->id);
            if ($record->status !== 'issued') {
                throw ValidationException::withMessages(['permit' => 'Only an issued permit may be revoked.']);
            }
            $record->update(['status' => 'revoked', 'active_permit_key' => null, 'revoked_by' => $admin->id, 'revoked_at' => now(), 'revocation_reason' => $reason]);
            $this->audit->record($admin, 'billing.permit.revoked', $record);

            return $record;
        });
    }
}
