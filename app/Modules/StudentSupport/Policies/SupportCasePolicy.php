<?php

namespace App\Modules\StudentSupport\Policies;

use App\Models\User;
use App\Modules\StudentSupport\Models\SupportCase;

class SupportCasePolicy
{
    public function viewAny(User $user): bool { return $user->hasAnyRole(['admin', 'registrar']); }
    public function view(User $user, SupportCase $case): bool { return $this->viewAny($user); }
    public function create(User $user): bool { return $this->viewAny($user); }
    public function update(User $user, SupportCase $case): bool { return $this->view($user, $case); }
    public function summary(User $user, SupportCase $case): bool { return $this->view($user, $case); }
}
