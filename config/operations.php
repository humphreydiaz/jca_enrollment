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
        // D-060: encrypted backups of the database and private documents, made only when an Admin clicks
        // "Back up now" (or runs php artisan jca:backup) and before each restore; never on a schedule.
        'path' => env('BACKUP_PATH') ?: storage_path('app/backups'),
        'keep' => max(1, (int) (env('BACKUP_KEEP') ?: 14)),
        // With no backup for this many days, Admin → Backups shows a reminder to make one.
        'remind_after_days' => max(1, (int) (env('BACKUP_REMIND_AFTER_DAYS') ?: 7)),
        // Optional base64 32-byte key. When empty, the key is derived from APP_KEY, so keep APP_KEY safe offsite.
        'key' => env('BACKUP_ENCRYPTION_KEY'),
        // Rows of these tables are runtime state (sessions, cache, queued jobs) and are never backed up or restored.
        'structure_only_tables' => ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'],
    ],
];
