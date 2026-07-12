<?php

namespace App\Modules\SectionAssignment\Policies;

use App\Models\User;
use App\Modules\SectionAssignment\Models\SectionAssignment;

class SectionAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'registrar']);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, SectionAssignment $assignment): bool
    {
        return $this->viewAny($user);
    }

    public function overrideCapacity(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
