<?php

namespace App\Modules\Enrollment\Events;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final class EnrollmentApproved implements ShouldDispatchAfterCommit
{
    public function __construct(public Enrollment $enrollment, public ?User $actor) {}
}
