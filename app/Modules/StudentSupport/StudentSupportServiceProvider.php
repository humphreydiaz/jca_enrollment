<?php

namespace App\Modules\StudentSupport;

use App\Modules\StudentSupport\Models\SupportCase;
use App\Modules\StudentSupport\Policies\SupportCasePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class StudentSupportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(SupportCase::class, SupportCasePolicy::class);
        Route::middleware('web')->group(base_path('routes/modules/support.php'));
    }
}
