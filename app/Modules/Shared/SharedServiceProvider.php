<?php

namespace App\Modules\Shared;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SharedServiceProvider extends ServiceProvider
{
    /**
     * Register shared module services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap shared module routes.
     */
    public function boot(): void
    {
        Route::middleware('web')->group(base_path('routes/modules/shared.php'));
    }
}
