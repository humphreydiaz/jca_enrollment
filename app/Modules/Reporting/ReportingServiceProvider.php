<?php

namespace App\Modules\Reporting;

use App\Models\User;
use App\Modules\Reporting\Console\PruneExpiredReportExports;
use App\Modules\Reporting\Models\ExportJob;
use App\Modules\Reporting\Policies\AuditLogPolicy;
use App\Modules\Reporting\Policies\ExportJobPolicy;
use App\Modules\Shared\Audit\Models\AuditLog;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ReportingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([PruneExpiredReportExports::class]);
    }

    public function boot(): void
    {
        Gate::policy(ExportJob::class, ExportJobPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        // D-060: backups hold every record and document, so only an active Admin may see or download them.
        Gate::define('manage-backups', fn (User $user): bool => $user->hasRole('admin') && $user->isActive());
        Route::middleware('web')->group(base_path('routes/modules/reporting.php'));
    }
}
