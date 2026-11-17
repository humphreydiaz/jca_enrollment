<?php

namespace App\Console\Commands;

use App\Modules\Shared\Backup\CreateBackupAction;
use Illuminate\Console\Command;
use Illuminate\Support\Number;
use Throwable;

class BackupCommand extends Command
{
    protected $signature = 'jca:backup {--label= : Short label added to the file name, for example before-hotfix}';

    protected $description = 'Create an encrypted backup of the database and private documents (D-060)';

    public function handle(CreateBackupAction $backup): int
    {
        try {
            $result = $backup->execute(null, $this->option('label') ?: null);
        } catch (Throwable $exception) {
            $this->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Backup created: {$result['name']} (".Number::fileSize($result['size'], 1).')');
        $this->line("Database: {$result['tables']} tables, {$result['rows']} rows. Documents: {$result['files']} files.");
        $this->line('Folder: '.config('operations.backup.path'));
        if ($result['pruned'] !== []) {
            $this->line('Removed older backups: '.implode(', ', $result['pruned']));
        }

        return self::SUCCESS;
    }
}
