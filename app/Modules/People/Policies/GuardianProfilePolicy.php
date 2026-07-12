<?php

namespace App\Modules\People\Policies;

use App\Models\User;
use App\Modules\People\Models\GuardianProfile;

class GuardianProfilePolicy
{
    public function view(User $user, GuardianProfile $guardian): bool
    {
        return $guardian->user_id === $user->id || $user->hasAnyRole(['admin', 'registrar']);
    }

    public function manage(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'registrar']);
    }
}
