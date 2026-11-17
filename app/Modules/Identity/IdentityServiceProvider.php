<?php

namespace App\Modules\Identity;

use App\Models\User;
use App\Modules\Identity\Console\BootstrapAdminCommand;
use App\Modules\Identity\Policies\UserPolicy;
use App\Modules\People\Console\BackfillStudentNumbers;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\People\Policies\GuardianProfilePolicy;
use App\Modules\People\Policies\StudentProfilePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    /**
     * Register identity services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Register policies and module routes.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(GuardianProfile::class, GuardianProfilePolicy::class);
        Gate::policy(StudentProfile::class, StudentProfilePolicy::class);

        Route::middleware('web')->group(base_path('routes/modules/identity.php'));

        if ($this->app->runningInConsole()) {
            $this->commands([BootstrapAdminCommand::class, BackfillStudentNumbers::class]);
        }
    }
}
