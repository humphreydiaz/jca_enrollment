<?php

namespace App\Modules\Enrollment\Events;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final class EnrollmentStatusChanged implements ShouldDispatchAfterCommit
{
    public function __construct(public Enrollment $enrollment, public string $from, public string $to, public ?User $actor) {}
}
