<?php

namespace App\Modules\Identity\Policies;

use App\Models\User;

class UserPolicy
{
    public function view(User $user, User $subject): bool
    {
        return $user->id === $subject->id || $user->hasRole('admin');
    }

    public function manage(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
