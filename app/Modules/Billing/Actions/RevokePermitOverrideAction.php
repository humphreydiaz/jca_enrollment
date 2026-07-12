<?php

namespace App\Modules\Billing\Actions;

use App\Models\User;
use App\Modules\Billing\Models\ExamPermitOverride;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RevokePermitOverrideAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function execute(ExamPermitOverride $override, string $reason, User $admin): ExamPermitOverride
    {
        return DB::transaction(function () use ($override, $reason, $admin): ExamPermitOverride {
            if (! $admin->hasRole('admin')) {
                throw ValidationException::withMessages(['override' => 'Admin authorization is required.']);
            }
            $record = ExamPermitOverride::query()->lockForUpdate()->findOrFail($override->id);
            if ($record->status !== 'approved') {
                throw ValidationException::withMessages(['override' => 'Only an approved override may be revoked.']);
            }
            $record->update(['status' => 'revoked', 'revoked_by' => $admin->id, 'revoked_at' => now(), 'revocation_reason' => $reason]);
            $this->audit->record($admin, 'billing.permit_override.revoked', $record);

            return $record;
        });
    }
}
