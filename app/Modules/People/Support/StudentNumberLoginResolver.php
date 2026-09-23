<?php

namespace App\Modules\People\Support;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Models\StudentProfile;

/**
 * Resolves a typed student number to the login account behind it. Before
 * approval the permanent profile is not yet connected to the student's login,
 * so the account is reached through the application that issued the number;
 * once connected, the profile's own account is used.
 */
class StudentNumberLoginResolver
{
    public function resolve(string $identifier): ?User
    {
        if (! StudentNumber::looksLike($identifier)) {
            return null;
        }

        $profile = StudentProfile::query()
            ->where('student_number', StudentNumber::normalize($identifier))
            ->first();

        if ($profile === null) {
            return null;
        }

        if ($profile->user_id !== null) {
            return User::query()->find($profile->user_id);
        }

        $applicantUserId = Enrollment::query()
            ->where('student_id', $profile->id)
            ->whereNotNull('student_applicant_user_id')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->value('student_applicant_user_id');

        return $applicantUserId !== null ? User::query()->find($applicantUserId) : null;
    }
}
