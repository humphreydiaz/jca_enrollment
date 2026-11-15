<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Support\TemporaryPassword;
use App\Modules\Shared\Audit\AuditRecorder;

class CreateTemporaryAccessUserAction
{
    public function __construct(
        private readonly AssignRolesAction $assignRoles,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(string $name, ?string $email, ?string $mobile, string $role, User $actor): CreatedTemporaryAccess
    {
        $temporaryPassword = TemporaryPassword::generate();
        $expiresAt = now()->addDays(3);
        $user = User::query()->create([
            'name' => $name,
            'email' => ContactNormalizer::email($email),
            'mobile' => ContactNormalizer::mobile($mobile),
            'password' => $temporaryPassword,
            'status' => AccountStatus::Active,
            'must_change_password' => true,
            'password_changed_at' => null,
            'temporary_access_expires_at' => $expiresAt,
        ]);
        $user = $this->assignRoles->execute($user, [$role], $actor);
        $this->audit->record($actor, 'identity.temporary_access.created', $user, [
            'temporary_access_expires_at' => $expiresAt->toIso8601String(),
        ]);

        return new CreatedTemporaryAccess($user, $temporaryPassword, $expiresAt);
    }
}
