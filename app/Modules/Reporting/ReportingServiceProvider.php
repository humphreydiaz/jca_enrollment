<?php

namespace App\Modules\Reporting;

use App\Modules\Reporting\Models\ExportJob;
use App\Modules\Reporting\Policies\AuditLogPolicy;
use App\Modules\Reporting\Policies\ExportJobPolicy;
use App\Modules\Shared\Audit\Models\AuditLog;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ReportingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(ExportJob::class, ExportJobPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Route::middleware('web')->group(base_path('routes/modules/reporting.php'));
    }
}
