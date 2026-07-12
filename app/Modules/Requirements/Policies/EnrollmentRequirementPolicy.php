<?php

namespace App\Modules\Requirements\Policies;

use App\Models\User;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use Illuminate\Auth\Access\Response;

class EnrollmentRequirementPolicy
{
    public function upload(User $user, EnrollmentRequirement $requirement): Response
    {
        return in_array($requirement->enrollment->status, ['draft', 'submitted', 'incomplete'], true)
            && ($this->owns($user, $requirement) || $user->hasAnyRole(['admin', 'registrar']))
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function view(User $user, EnrollmentRequirement $requirement): Response
    {
        return $this->owns($user, $requirement) || $user->hasAnyRole(['admin', 'registrar'])
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function review(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'registrar']);
    }

    public function waive(User $user): bool
    {
        return $user->hasRole('admin');
    }

    private function owns(User $user, EnrollmentRequirement $requirement): bool
    {
        $guardian = $user->guardianProfile;
        if ($guardian === null) {
            return false;
        }

        return $requirement->enrollment->applicant_guardian_id === $guardian->id
            || $requirement->enrollment->student->guardians()
                ->whereKey($guardian->id)
                ->wherePivotNotNull('verified_at')
                ->wherePivot('may_manage_enrollment', true)
                ->exists();
    }
}
