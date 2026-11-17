<?php

namespace App\Modules\People\Support;

use App\Models\User;
use App\Modules\People\Models\StaffProfile;

class StaffNumberLoginResolver
{
    public function resolve(string $identifier): ?User
    {
        if (! StaffNumber::looksLike($identifier)) {
            return null;
        }

        return StaffProfile::query()
            ->where('employee_number', StaffNumber::normalize($identifier))
            ->first()
            ?->user;
    }
}
