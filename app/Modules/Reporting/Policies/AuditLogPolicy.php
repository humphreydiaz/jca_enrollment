<?php

namespace App\Modules\Reporting\Policies;

use App\Models\User;
use App\Modules\Shared\Audit\Models\AuditLog;

class AuditLogPolicy
{
    public const PROFILE_UPDATE_ACTIONS = [
        'people.student.self_profile_updated',
        'people.guardian.self_profile_updated',
    ];

    /** The full audit trail is Admin-only. */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /** "Information updates": changes students and parents made to their own My Information. */
    public function viewInformationUpdates(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'registrar']);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('registrar')
            && in_array($auditLog->action, self::PROFILE_UPDATE_ACTIONS, true);
    }
}
