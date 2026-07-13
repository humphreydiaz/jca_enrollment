<?php

use App\Modules\Academics\AcademicsServiceProvider;
use App\Modules\Billing\BillingServiceProvider;
use App\Modules\Communications\CommunicationsServiceProvider;
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
    BillingServiceProvider::class,
    CommunicationsServiceProvider::class,
    EnrollmentServiceProvider::class,
    SectionAssignmentServiceProvider::class,
    StudentSupportServiceProvider::class,
];
