<?php

namespace App\Modules\Communications;

use App\Modules\Communications\Contracts\SmsGateway;
use App\Modules\Communications\Gateways\DisabledSmsGateway;
use App\Modules\Communications\Gateways\LogSmsGateway;
use App\Modules\Communications\Gateways\SemaphoreSmsGateway;
use App\Modules\Communications\Models\Announcement;
use App\Modules\Communications\Policies\AnnouncementPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CommunicationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, function (): SmsGateway {
            if (! config('communications.sms.enabled')) {
                return new DisabledSmsGateway;
            }

            return match (config('communications.sms.driver')) {
                'semaphore' => new SemaphoreSmsGateway,
                'log' => new LogSmsGateway,
                default => new DisabledSmsGateway,
            };
        });
    }

    public function boot(): void
    {
        Gate::policy(Announcement::class, AnnouncementPolicy::class);
        Route::middleware('web')->group(base_path('routes/modules/communications.php'));
    }
}
