<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\AccountInvitation;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReactivateUserAction
{
    public function __construct(
        private readonly CreateAccountInvitationAction $createInvitation,
        private readonly ReissueTemporaryPasswordAction $reissuePassword,
        private readonly RevokeUserSessionsAction $revokeUserSessions,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function execute(User $user, User $actor, string $reason): array
    {
        abort_unless($actor->hasRole('admin'), 403);

        return DB::transaction(function () use ($actor, $reason, $user): array {
            $account = User::query()
                ->with('roles')
                ->lockForUpdate()
                ->findOrFail($user->id);

            if (
                $account->disabled_at === null &&
                $account->status !== AccountStatus::Disabled
            ) {
                throw ValidationException::withMessages([
                    'account' => 'Only disabled accounts can be reactivated.',
                ]);
            }

            if ($account->locked_at !== null) {
                throw ValidationException::withMessages([
                    'account' => 'This account requires a separate security review.',
                ]);
            }

            $role = $account->roles->sole()->code;

            $staffRoles = [
                'admin',
                'registrar',
                'accounting',
                'teacher',
            ];

            if (in_array($role, $staffRoles, true)) {
                $channel = $account->email !== null
                    ? 'email'
                    : 'mobile';

                $sentTo = $channel === 'email'
                    ? $account->email
                    : $account->mobile;

                if ($sentTo === null) {
                    throw ValidationException::withMessages([
                        'account' => 'A verified contact channel is required for staff invitation.',
                    ]);
                }

                $account->forceFill([
                    'status' => AccountStatus::Invited,
                    'disabled_at' => null,
                    'must_change_password' => true,
                ])->save();

                $this->revokeUserSessions->execute($account);

                $this->auditRecorder->record(
                    $actor,
                    'identity.user.reactivated',
                    $account,
                    ['reason' => $reason, 'method' => 'staff_invitation']
                );

                $created = $this->createInvitation->execute(
                    $account,
                    $channel,
                    $sentTo,
                    $actor
                );

                return [
                    'type' => 'staff',
                    'invitation' => $created,
                ];
            }

            if (! in_array($role, ['guardian', 'student'], true)) {
                throw ValidationException::withMessages([
                    'account' => 'Unsupported account role for reactivation.',
                ]);
            }

            // Parent and Student accounts do not use staff invitations.
            $account->forceFill([
                'status' => AccountStatus::Active,
                'disabled_at' => null,
            ])->save();

            // Use the existing temporary-password system.
            $issued = $this->reissuePassword->execute(
                $account,
                $actor
            );

            // Revoke old unused invitation links, preserving history.
            AccountInvitation::query()
                ->where('user_id', $account->id)
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'revoked_by' => $actor->id,
                ]);

            $this->auditRecorder->record(
                $actor,
                'identity.user.reactivated',
                $account,
                ['reason' => $reason, 'method' => 'temporary_access']
            );

            return [
                'type' => 'temporary_access',
                'temporary_password' => $issued['temporary_password'],
                'expires_at' => $issued['expires_at'],
            ];
        });
    }
}