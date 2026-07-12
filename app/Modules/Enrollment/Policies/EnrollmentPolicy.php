<?php

namespace App\Modules\Enrollment\Policies;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;

class EnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'registrar']);
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        if ($user->hasAnyRole(['admin', 'registrar'])) {
            return true;
        }

        if ($user->studentProfile?->id === $enrollment->student_id) {
            return true;
        }

        $guardian = $user->guardianProfile;

        return $guardian !== null && (
            $enrollment->applicant_guardian_id === $guardian->id
            || $enrollment->student->guardians()
                ->whereKey($guardian->id)
                ->wherePivotNotNull('verified_at')
                ->wherePivot('may_manage_enrollment', true)
                ->exists()
        );
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $user->hasAnyRole(['admin', 'registrar']);
    }
}
