<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReactivateUserAction
{
    public function __construct(
        private readonly CreateAccountInvitationAction $createInvitation,
        private readonly RevokeUserSessionsAction $revokeUserSessions,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function execute(User $user, User $actor, string $reason): CreatedInvitation
    {
        return DB::transaction(function () use ($actor, $reason, $user): CreatedInvitation {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($lockedUser->disabled_at === null && $lockedUser->status !== AccountStatus::Disabled) {
                throw ValidationException::withMessages([
                    'account' => 'Only a disabled account can be reactivated.',
                ]);
            }

            if ($lockedUser->locked_at !== null) {
                throw ValidationException::withMessages([
                    'account' => 'This account is locked and requires a separate security review before reactivation.',
                ]);
            }

            $channel = $lockedUser->email !== null ? 'email' : 'mobile';
            $sentTo = $channel === 'email' ? $lockedUser->email : $lockedUser->mobile;

            if ($sentTo === null) {
                throw ValidationException::withMessages([
                    'account' => 'This account has no verified contact channel for a setup invitation.',
                ]);
            }

            $lockedUser->forceFill([
                'status' => AccountStatus::Invited,
                'disabled_at' => null,
                'must_change_password' => true,
            ])->save();

            $this->revokeUserSessions->execute($lockedUser);
            $this->auditRecorder->record($actor, 'identity.user.reactivated', $lockedUser, [
                'reason' => $reason,
            ]);

            return $this->createInvitation->execute(
                $lockedUser,
                $channel,
                $sentTo,
                $actor,
            );
        });
    }
}
