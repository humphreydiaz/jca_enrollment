<?php

namespace App\Modules\Enrollment\Actions;

use App\Modules\Enrollment\Models\Enrollment;

class StartedEnrollment
{
    public function __construct(
        public readonly Enrollment $enrollment,
        public readonly string $resumeToken,
        public readonly string $verificationCode,
    ) {}
}
