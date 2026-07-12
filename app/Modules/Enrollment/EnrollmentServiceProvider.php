<?php

namespace App\Modules\Enrollment;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\Enrollment\Policies\EnrollmentPolicy;
use App\Modules\Requirements\Policies\EnrollmentRequirementPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class EnrollmentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Enrollment::class, EnrollmentPolicy::class);
        Gate::policy(EnrollmentRequirement::class, EnrollmentRequirementPolicy::class);
        Route::middleware('web')->group(base_path('routes/modules/enrollment.php'));
    }
}
