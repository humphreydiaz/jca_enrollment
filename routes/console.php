<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Existing scheduled tasks
Schedule::command('auth:clear-resets')
    ->daily()
    ->withoutOverlapping();

Schedule::command(
    'queue:prune-failed --hours='.
    (int) config('operations.queue.prune_failed_hours')
)->daily()->withoutOverlapping();

Schedule::command(
    'queue:prune-batches --hours='.
    (int) config('operations.queue.prune_batch_hours').
    ' --unfinished='.
    (int) config('operations.queue.prune_batch_hours').
    ' --cancelled='.
    (int) config('operations.queue.prune_batch_hours')
)->daily()->withoutOverlapping();

Schedule::command('reports:prune-exports')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Automatic encrypted backup
// Every day at 2:00 AM Philippine time
Schedule::command('jca:backup --label=automatic-daily')
    ->dailyAt('02:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();

// Automatic Grade 10 graduation using Academic Year end date
Schedule::command('app:graduate-eligible-grade10-students')
    ->dailyAt('03:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();
