<?php

namespace App\Modules\Grading;

use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Grading\Policies\StudentSubjectGradePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class GradingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(StudentSubjectGrade::class, StudentSubjectGradePolicy::class);
        Route::middleware('web')->group(base_path('routes/modules/grading.php'));
    }
}
