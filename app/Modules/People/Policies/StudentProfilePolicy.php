<?php

namespace App\Modules\People\Policies;

use App\Models\User;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\StudentProfile;

class StudentProfilePolicy
{
    public function view(User $user, StudentProfile $student): bool
    {
        if ($student->user_id === $user->id || $user->hasAnyRole(['admin', 'registrar'])) {
            return true;
        }

        $guardianId = $user->guardianProfile?->id;

        if ($guardianId === null) {
            return false;
        }

        return GuardianStudent::query()
            ->where('guardian_id', $guardianId)
            ->where('student_id', $student->id)
            ->whereNull('ended_at')
            ->where('may_manage_enrollment', true)
            ->exists();
    }

    public function manage(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'registrar']);
    }
}
