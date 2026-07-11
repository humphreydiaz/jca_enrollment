<?php

namespace App\Modules\Requirements\Policies;

use App\Models\User;
use App\Modules\Enrollment\Models\EnrollmentRequirement;

class EnrollmentRequirementPolicy
{
    public function upload(User $user, EnrollmentRequirement $requirement): bool
    {
        return $this->owns($user, $requirement) || $user->hasAnyRole(['admin', 'registrar']);
    }

    public function view(User $user, EnrollmentRequirement $requirement): bool
    {
        return $this->upload($user, $requirement);
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
        return $user->guardianProfile !== null && $requirement->enrollment->applicant_guardian_id === $user->guardianProfile->id;
    }
}
