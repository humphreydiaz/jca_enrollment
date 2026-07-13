<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('auth:clear-resets')->daily()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours='.(int) config('operations.queue.prune_failed_hours'))->daily()->withoutOverlapping();
Schedule::command('queue:prune-batches --hours='.(int) config('operations.queue.prune_batch_hours').' --unfinished='.(int) config('operations.queue.prune_batch_hours').' --cancelled='.(int) config('operations.queue.prune_batch_hours'))->daily()->withoutOverlapping();
Schedule::command('reports:prune-exports')->hourly()->withoutOverlapping()->onOneServer();
