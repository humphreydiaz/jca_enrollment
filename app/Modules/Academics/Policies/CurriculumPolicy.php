<?php

namespace App\Modules\Academics\Policies;

use App\Models\User;
use App\Modules\Academics\Models\Curriculum;

class CurriculumPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'registrar']);
    }

    public function view(User $user, Curriculum $curriculum): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Curriculum $curriculum): bool
    {
        return $user->hasRole('admin') && $curriculum->status === 'draft';
    }

    public function publish(User $user, Curriculum $curriculum): bool
    {
        return $this->update($user, $curriculum);
    }

    public function delete(User $user, Curriculum $curriculum): bool
    {
        return $user->hasRole('admin')
            && $curriculum->status === 'draft'
            && $curriculum->published_at === null
            && $curriculum->published_by === null;
    }
}
