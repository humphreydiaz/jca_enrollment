<?php

namespace App\Modules\Reporting\Policies;

use App\Models\User;
use App\Modules\Shared\Audit\Models\AuditLog;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'registrar']);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('registrar')
            && in_array($auditLog->action, [
                'people.student.self_profile_updated',
                'people.guardian.self_profile_updated',
            ], true);
    }
}
