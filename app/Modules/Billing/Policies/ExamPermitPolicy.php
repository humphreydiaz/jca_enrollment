<?php

namespace App\Modules\Billing\Policies;

use App\Models\User;
use App\Modules\Billing\Models\ExamPermit;

class ExamPermitPolicy
{
    public function view(User $user, ExamPermit $permit): bool
    {
        if ($user->hasAnyRole(['admin', 'accounting', 'registrar'])) {
            return true;
        }

        return $user->can('view', $permit->assessment);
    }

    public function issue(User $user): bool
    {
        return $user->hasRole('accounting');
    }

    public function print(User $user, ExamPermit $permit): bool
    {
        return $permit->status === 'issued' && $this->view($user, $permit);
    }
}
