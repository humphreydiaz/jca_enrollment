<?php

use App\Modules\Academics\AcademicsServiceProvider;
use App\Modules\Enrollment\EnrollmentServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\SectionAssignment\SectionAssignmentServiceProvider;
use App\Modules\Shared\SharedServiceProvider;
use App\Modules\StudentSupport\StudentSupportServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    SharedServiceProvider::class,
    IdentityServiceProvider::class,
    AcademicsServiceProvider::class,
    EnrollmentServiceProvider::class,
    SectionAssignmentServiceProvider::class,
    StudentSupportServiceProvider::class,
];
