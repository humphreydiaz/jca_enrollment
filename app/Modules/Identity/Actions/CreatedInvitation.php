<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\AccountInvitation;

class CreatedInvitation
{
    public function __construct(
        public readonly AccountInvitation $invitation,
        public readonly string $token,
    ) {}
}
