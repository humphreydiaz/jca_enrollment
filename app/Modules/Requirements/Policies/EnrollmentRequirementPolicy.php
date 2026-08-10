<?php

namespace App\Modules\Requirements\Policies;

use App\Models\User;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use Illuminate\Auth\Access\Response;

class EnrollmentRequirementPolicy
{
    private const SOFT_COPY_UPLOAD_STATUSES = ['submitted', 'under_review', 'incomplete', 'assessment_required', 'assessment_pending', 'waitlisted'];

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

    public function upload(User $user, EnrollmentRequirement $requirement): Response
    {
        $allowed = $user->hasAnyRole(['student', 'guardian'])
            && $this->owns($user, $requirement)
            && in_array($requirement->enrollment->status, self::SOFT_COPY_UPLOAD_STATUSES, true);

        return $allowed ? Response::allow() : Response::denyAsNotFound();
    }

    public function waive(User $user): bool
    {
        return $user->hasRole('admin');
    }

    private function owns(User $user, EnrollmentRequirement $requirement): bool
    {
        if ($requirement->enrollment->student_applicant_user_id === $user->id && $user->hasRole('student')) {
            return true;
        }

        if ($user->studentProfile?->id === $requirement->enrollment->student_id) {
            return true;
        }

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
