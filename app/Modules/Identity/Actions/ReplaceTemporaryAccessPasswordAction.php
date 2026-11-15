<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Support\TemporaryPassword;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

class ReplaceTemporaryAccessPasswordAction
{
    public function __construct(
        private readonly RevokeUserSessionsAction $revokeSessions,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(User $user, User $actor): CreatedTemporaryAccess
    {
        $record = User::query()->lockForUpdate()->findOrFail($user->id);
        if (! $record->must_change_password || $record->disabled_at !== null || $record->locked_at !== null) {
            throw ValidationException::withMessages([
                'application' => $record->must_change_password
                    ? 'This account is disabled or locked and requires Admin review.'
                    : 'The owner has already established a private password. Use the audited recovery workflow instead.',
            ]);
        }

        $temporaryPassword = TemporaryPassword::generate();
        $expiresAt = now()->addDays(3);
        $record->forceFill([
            'password' => $temporaryPassword,
            'password_changed_at' => null,
            'temporary_access_expires_at' => $expiresAt,
            'temporary_access_used_at' => null,
        ])->save();
        $this->revokeSessions->execute($record);
        $this->audit->record($actor, 'identity.temporary_access.replaced', $record, [
            'temporary_access_expires_at' => $expiresAt->toIso8601String(),
        ]);

        return new CreatedTemporaryAccess($record, $temporaryPassword, $expiresAt);
    }
}
