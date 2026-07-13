<?php

namespace App\Modules\Reporting\Policies;

use App\Models\User;
use App\Modules\Shared\Audit\Models\AuditLog;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->hasRole('admin');
    }
}
