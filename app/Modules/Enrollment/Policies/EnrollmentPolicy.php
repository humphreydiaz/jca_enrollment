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

        if ((int) $enrollment->access_issued_to_user_id === (int) $user->id) {
            return true;
        }

        if ((int) $enrollment->student_applicant_user_id === (int) $user->id) {
            return true;
        }

        if ($user->studentProfile &&
            (int) $user->studentProfile->id === (int) $enrollment->student_id) {
            return true;
        }

        $guardian = $user->guardianProfile;

        if (! $guardian) {
            return false;
        }

        if ((int) $enrollment->applicant_guardian_id === (int) $guardian->id) {
            return true;
        }

        return $enrollment->student
            ->guardians()
            ->whereKey($guardian->id)
            ->wherePivotNotNull('verified_at')
            ->exists();
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        if ($enrollment->status !== 'draft') {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'registrar'])) {
            return true;
        }

        if ((int) $enrollment->access_issued_to_user_id === (int) $user->id) {
            return true;
        }

        if ((int) $enrollment->student_applicant_user_id === (int) $user->id) {
            return true;
        }

        if ($user->studentProfile &&
            (int) $user->studentProfile->id === (int) $enrollment->student_id) {
            return true;
        }

        $guardian = $user->guardianProfile;

        if (! $guardian) {
            return false;
        }

        return $enrollment->student
            ->guardians()
            ->whereKey($guardian->id)
            ->wherePivotNotNull('verified_at')
            ->wherePivot('may_manage_enrollment', true)
            ->exists();
    }
}