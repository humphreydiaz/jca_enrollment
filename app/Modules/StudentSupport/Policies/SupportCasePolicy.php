<?php

namespace App\Modules\StudentSupport\Policies;

use App\Models\User;
use App\Modules\StudentSupport\Models\SupportCase;

class SupportCasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'registrar']);
    }

    public function view(User $user, SupportCase $case): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, SupportCase $case): bool
    {
        return $this->view($user, $case);
    }

    public function assign(User $user, SupportCase $case): bool
    {
        return $user->hasRole('admin') || $user->hasRole('registrar');
    }

    public function record(User $user, SupportCase $case): bool
    {
        return $user->hasRole('admin') || ($user->hasRole('registrar') && $case->assigned_reviewer_id === $user->id);
    }

    public function summary(User $user, SupportCase $case): bool
    {
        return $this->view($user, $case);
    }
}
