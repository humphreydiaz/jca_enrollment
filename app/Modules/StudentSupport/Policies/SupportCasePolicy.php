<?php

namespace App\Modules\StudentSupport\Policies;

use App\Models\User;
use App\Modules\StudentSupport\Models\SupportCase;
use Illuminate\Auth\Access\Response;

class SupportCasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'registrar']);
    }

    public function view(User $user, SupportCase $case): Response
    {
        return $user->hasRole('admin') || ($user->hasRole('registrar') && $case->assigned_reviewer_id === $user->id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, SupportCase $case): Response
    {
        return $this->view($user, $case);
    }

    public function assign(User $user, SupportCase $case): Response
    {
        return $user->hasRole('admin') ? Response::allow() : Response::denyAsNotFound();
    }

    public function record(User $user, SupportCase $case): Response
    {
        return $this->view($user, $case);
    }

    public function summary(User $user, SupportCase $case): Response
    {
        return $this->view($user, $case);
    }
}
