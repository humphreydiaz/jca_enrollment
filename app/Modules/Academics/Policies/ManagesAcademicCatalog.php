<?php

namespace App\Modules\Academics\Policies;

use App\Models\User;

trait ManagesAcademicCatalog
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'registrar']);
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user): bool
    {
        return $this->viewAny($user);
    }

    /** Archiving or restoring a catalog record is an Admin decision. */
    public function archive(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /** Only unused records can be deleted, and only by the Admin. */
    public function delete(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
