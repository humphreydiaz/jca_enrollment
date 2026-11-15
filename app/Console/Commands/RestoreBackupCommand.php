<?php

namespace App\Console\Commands;

use App\Modules\Shared\Backup\RestoreBackupAction;
use Illuminate\Console\Command;
use Throwable;

class RestoreBackupCommand extends Command
{
    protected $signature = 'jca:restore
        {file : Backup file name in the backup folder, or a full path to a .jcabak file}
        {--drill-database= : Restore into this separate empty database instead of the live one (restore drill)}
        {--drill-files= : Folder for the drill copy of the documents (default storage/app/restore-drill-...)}
        {--inspect : Only decrypt and verify the backup, then show what it contains}
        {--allow-different-app-key : Restore even though the backup was made with another APP_KEY}
        {--force : Do not ask for confirmation}';

    protected $description = 'Verify and restore an encrypted JCA backup, or rehearse it in a separate database (D-060)';

    public function handle(RestoreBackupAction $restore): int
    {
        $file = (string) $this->argument('file');
        try {
            $manifest = $restore->inspect($file);
        } catch (Throwable $exception) {
            $this->error('Cannot use this backup: '.$exception->getMessage());

            return self::FAILURE;
        }

        $rows = array_sum($manifest['database']['rows'] ?? []);
        $this->info('Backup verified: every checksum matches.');
        $this->table(['Created', 'Database', 'Tables', 'Rows', 'Migrations', 'Documents'], [[
            $manifest['created_at'] ?? '?', $manifest['database']['name'] ?? '?', count($manifest['database']['rows'] ?? []),
            $rows, $manifest['database']['migrations'] ?? '?', count($manifest['files'] ?? []),
        ]]);
        if ($this->option('inspect')) {
            return self::SUCCESS;
        }

        $drill = $this->option('drill-database') ?: null;
        if ($drill === null) {
            $this->warn('This replaces EVERY table of the live database and the private documents with the backup.');
            $this->warn('A safety backup of the current state is taken first; current documents are moved aside, not deleted.');
            if (! $this->option('force') && $this->ask('Type RESTORE to continue') !== 'RESTORE') {
                $this->line('Restore cancelled.');

                return self::FAILURE;
            }
        }

        try {
            $result = $drill === null
                ? $restore->restoreLive($file, null, 'command line', (bool) $this->option('allow-different-app-key'))
                : $restore->execute($file, [
                    'drill_database' => $drill,
                    'drill_files' => $this->option('drill-files') ?: null,
                    'allow_different_app_key' => (bool) $this->option('allow-different-app-key'),
                ]);
        } catch (Throwable $exception) {
            $this->error('Restore failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(ucfirst($result['mode'])." restore finished: {$result['statements']} SQL statements, {$result['files']} documents.");
        $this->line('Documents restored to: '.$result['files_path']);
        if ($result['safety_backup'] !== null) {
            $this->line('Safety backup of the state before the restore: '.$result['safety_backup']);
        }
        if ($result['previous_files'] !== null) {
            $this->line('Documents that were in place before are kept at: '.$result['previous_files']);
        }
        if ($result['row_mismatches'] === []) {
            $this->info('Row counts match the backup for every table.');
        } else {
            foreach ($result['row_mismatches'] as $table => $counts) {
                $this->warn("{$table}: expected {$counts['expected']} rows, found {$counts['actual']}.");
            }

            return self::FAILURE;
        }
        if ($drill === null) {
            $this->line('If this code is newer than the backup, run: php artisan migrate --force');
        }

        return self::SUCCESS;
    }
}
