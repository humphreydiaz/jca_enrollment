<?php

namespace App\Modules\Academics\Policies;

use App\Models\User;
use App\Modules\Academics\Models\TeachingAssignment;

class TeachingAssignmentPolicy
{
    use ManagesAcademicCatalog;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'registrar', 'teacher']);
    }

    public function view(User $user, TeachingAssignment $assignment): bool
    {
        return $user->hasAnyRole(['admin', 'registrar']) || TeachingAssignment::query()
            ->active()
            ->forUser($user)
            ->whereKey($assignment->id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'registrar']);
    }

    public function update(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'registrar']);
    }
}
