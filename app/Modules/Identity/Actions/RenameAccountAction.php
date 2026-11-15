<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Shared\Audit\AuditRecorder;

/** Updates an account's display name (e.g. a walk-in guardian account once the guardian's real name is known). */
class RenameAccountAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(User $account, string $name, User $actor): User
    {
        $name = trim($name);
        if ($name === '' || $account->name === $name) {
            return $account;
        }

        $account->forceFill(['name' => $name])->save();
        $this->audit->record($actor, 'identity.account.renamed', $account);

        return $account;
    }
}
