<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\AccountInvitation;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RedeemInvitationAction
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly RevokeUserSessionsAction $revokeUserSessions,
    ) {}

    public function execute(AccountInvitation $invitation, string $token, string $password): void
    {
        DB::transaction(function () use ($invitation, $password, $token): void {
            $lockedInvitation = AccountInvitation::query()
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedInvitation->isUsable() || ! hash_equals($lockedInvitation->token_hash, hash('sha256', $token))) {
                throw ValidationException::withMessages([
                    'invitation' => 'This account invitation is invalid or has expired.',
                ]);
            }

            $user = $lockedInvitation->user()->lockForUpdate()->firstOrFail();

            if ($user->disabled_at !== null || $user->locked_at !== null) {
                throw ValidationException::withMessages([
                    'invitation' => 'This account is not available for setup. Please contact the school.',
                ]);
            }

            $attributes = [
                'password' => $password,
                'status' => AccountStatus::Active,
                'must_change_password' => false,
                'password_changed_at' => now(),
            ];

            if ($lockedInvitation->channel === 'email') {
                $attributes['email_verified_at'] = now();
            }

            if ($lockedInvitation->channel === 'mobile') {
                $attributes['mobile_verified_at'] = now();
            }

            $user->forceFill($attributes)->save();
            $lockedInvitation->update(['consumed_at' => now()]);
            $this->revokeUserSessions->execute($user);
            $this->auditRecorder->record($user, 'identity.invitation.redeemed', $user, [
                'channel' => $lockedInvitation->channel,
            ]);
        });
    }
}
