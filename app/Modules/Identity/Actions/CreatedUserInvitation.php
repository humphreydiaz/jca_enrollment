<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;

class CreatedUserInvitation
{
    public function __construct(
        public readonly User $user,
        public readonly CreatedInvitation $invitation,
    ) {}
}
