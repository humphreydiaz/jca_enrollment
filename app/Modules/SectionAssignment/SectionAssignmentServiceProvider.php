<?php

namespace App\Modules\SectionAssignment;

use App\Modules\SectionAssignment\Models\SectionAssignment;
use App\Modules\SectionAssignment\Policies\SectionAssignmentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SectionAssignmentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(SectionAssignment::class, SectionAssignmentPolicy::class);
        Route::middleware('web')->group(base_path('routes/modules/section-assignment.php'));
    }
}
