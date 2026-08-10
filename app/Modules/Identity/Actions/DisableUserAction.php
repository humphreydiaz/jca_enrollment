<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\AccountInvitation;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisableUserAction
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly RevokeUserSessionsAction $revokeUserSessions,
    ) {}

    public function execute(User $user, User $actor, string $reason): void
    {
        DB::transaction(function () use ($actor, $reason, $user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($lockedUser->disabled_at !== null || $lockedUser->status === AccountStatus::Disabled) {
                throw ValidationException::withMessages([
                    'account' => 'This account is already disabled.',
                ]);
            }

            $lockedUser->forceFill([
                'status' => AccountStatus::Disabled,
                'disabled_at' => now(),
            ])->save();

            AccountInvitation::query()
                ->where('user_id', $lockedUser->id)
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'revoked_by' => $actor->id,
                ]);

            $this->revokeUserSessions->execute($lockedUser);
            $this->auditRecorder->record($actor, 'identity.user.disabled', $lockedUser, [
                'reason' => $reason,
            ]);
        });
    }
}
