<?php

return [
    'queue' => [
        'max_pending_jobs' => (int) env('QUEUE_ALERT_PENDING_JOBS', 100),
        'max_failed_jobs' => (int) env('QUEUE_ALERT_FAILED_JOBS', 0),
        'prune_failed_hours' => (int) env('QUEUE_PRUNE_FAILED_HOURS', 720),
        'prune_batch_hours' => (int) env('QUEUE_PRUNE_BATCH_HOURS', 168),
    ],
    'backup' => [
        'encrypted' => (bool) env('BACKUP_ENCRYPTION_CONFIGURED', false),
        'offsite' => (bool) env('BACKUP_OFFSITE_CONFIGURED', false),
        'monitoring' => (bool) env('BACKUP_MONITORING_CONFIGURED', false),
    ],
];
