<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Support\TemporaryPassword;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ReissueTemporaryPasswordAction
{
    public function __construct(
        private readonly RevokeUserSessionsAction $revokeSessions,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(User $user, User $actor): array
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages([
                'account_id' => 'You cannot reissue your own password here.',
            ]);
        }

        if ($user->hasRole('admin') && ! $actor->hasRole('admin')) {
            abort(403, 'Only an administrator can reset an administrator account.');
        }

        return DB::transaction(function () use ($user, $actor): array {
            $account = User::query()
                ->lockForUpdate()
                ->findOrFail($user->id);

            if (
                $account->disabled_at !== null ||
                $account->locked_at !== null ||
                $account->status === AccountStatus::Disabled
            ) {
                throw ValidationException::withMessages([
                    'account_id' => 'This account is disabled or locked. A separate account review is required.',
                ]);
            }

            $temporaryPassword = TemporaryPassword::generate();
            $expiresAt = now()->addDays(3);

            $account->forceFill([
                'password' => Hash::make($temporaryPassword),
                'status' => AccountStatus::Active,
                'must_change_password' => true,
                'password_changed_at' => null,
                'temporary_access_expires_at' => $expiresAt,
            ])->save();

            $this->revokeSessions->execute($account);

            $this->audit->record(
                $actor,
                'identity.temporary_password.reissued',
                $account,
                [
                    'reason' => 'Authorized account recovery',
                    'temporary_access_expires_at' => $expiresAt->toIso8601String(),
                ]
            );

            return [
                'temporary_password' => $temporaryPassword,
                'expires_at' => $expiresAt,
            ];
        });
    }
}