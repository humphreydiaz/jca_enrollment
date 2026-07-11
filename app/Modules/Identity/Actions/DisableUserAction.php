<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

class DisableUserAction
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly RevokeUserSessionsAction $revokeUserSessions,
    ) {}

    public function execute(User $user, User $actor): void
    {
        DB::transaction(function () use ($actor, $user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            $lockedUser->forceFill([
                'status' => AccountStatus::Disabled,
                'disabled_at' => now(),
            ])->save();
            $this->revokeUserSessions->execute($lockedUser);
            $this->auditRecorder->record($actor, 'identity.user.disabled', $lockedUser);
        });
    }
}
