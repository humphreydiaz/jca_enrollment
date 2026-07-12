<?php

namespace App\Modules\Billing;

use App\Modules\Billing\Listeners\AwardApprovedEnrollmentDiscount;
use App\Modules\Billing\Models\ExamPermit;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Policies\ExamPermitPolicy;
use App\Modules\Billing\Policies\FeeAssessmentPolicy;
use App\Modules\Billing\Policies\PaymentPolicy;
use App\Modules\Enrollment\Events\EnrollmentApproved;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(FeeAssessment::class, FeeAssessmentPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(ExamPermit::class, ExamPermitPolicy::class);
        Event::listen(EnrollmentApproved::class, AwardApprovedEnrollmentDiscount::class);
        Route::middleware('web')->group(base_path('routes/modules/billing.php'));
    }
}
