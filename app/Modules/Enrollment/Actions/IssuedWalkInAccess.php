<?php

namespace App\Modules\Enrollment\Actions;

use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Support\Carbon;

final readonly class IssuedWalkInAccess
{
    public function __construct(
        public Enrollment $enrollment,
        public string $studentNumber,
        public string $temporaryPassword,
        public string $accountOwnerName,
        public string $accountOwnerType,
        public Carbon $expiresAt,
    ) {}
}
