<?php

namespace App\Modules\Shared\Backup;

use App\Models\User;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * D-060: one encrypted backup file holding everything needed to recover the school's records together:
 * the database (database.sql), every private document under storage/app/private (files/...), and a manifest
 * with row counts and a SHA-256 checksum for each part.
 */
class CreateBackupAction
{
    public function __construct(
        private readonly BackupStore $store,
        private readonly BackupCipher $cipher,
        private readonly DatabaseDumper $dumper,
        private readonly AuditRecorder $audit,
    ) {}

    /** @return array{name: string, size: int, tables: int, rows: int, files: int, pruned: list<string>} */
    public function execute(?User $actor = null, ?string $label = null): array
    {
        BackupCipher::assertAvailable();
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required for backups.');
        }

        $this->store->writeStatus(['last_attempt_at' => now()->toIso8601String()]);
        $work = $this->store->temporaryDirectory();
        try {
            $connection = DB::connection();
            $sqlPath = $work.'/database.sql';
            $counts = $this->dumper->dump($connection, $sqlPath, (array) config('operations.backup.structure_only_tables'));

            $zipPath = $work.'/backup.zip';
            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Cannot create the backup archive.');
            }
            $zip->addFile($sqlPath, 'database.sql');
            $files = [];
            $root = rtrim((string) config('filesystems.disks.local.root'), '/\\');
            if (is_dir($root)) {
                $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
                foreach ($items as $item) {
                    $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
                    if (! $item->isFile() || $relative === '.gitignore') {
                        continue;
                    }
                    $zip->addFile($item->getPathname(), 'files/'.$relative);
                    $files[$relative] = ['size' => $item->getSize(), 'sha256' => hash_file('sha256', $item->getPathname())];
                }
            }

            $migrations = $connection->getSchemaBuilder()->hasTable('migrations') ? (int) $connection->table('migrations')->count() : 0;
            $manifest = [
                'format' => 1,
                'application' => config('app.name'),
                'environment' => app()->environment(),
                'created_at' => now()->toIso8601String(),
                'database' => ['driver' => $connection->getDriverName(), 'server_version' => $connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION),
                    'name' => $connection->getDatabaseName(), 'sha256' => hash_file('sha256', $sqlPath), 'migrations' => $migrations, 'rows' => $counts],
                'files' => $files,
                // Encrypted columns (for example SMS bodies) need the same APP_KEY; only a fingerprint is stored.
                'app_key_fingerprint' => substr(hash('sha256', (string) config('app.key')), 0, 16),
            ];
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            if ($zip->close() !== true) {
                throw new RuntimeException('Cannot finish the backup archive.');
            }

            $name = $this->store->newName($label);
            $target = $this->store->path($name);
            $this->cipher->encrypt($zipPath, $target.'.part');
            rename($target.'.part', $target);
            $pruned = $this->store->prune();

            $size = (int) filesize($target);
            $this->store->writeStatus(['last_success_at' => now()->toIso8601String(), 'last_success_name' => $name, 'last_success_size' => $size, 'last_error' => null]);
            $this->audit->record($actor, 'operations.backup.created', null, [
                'name' => $name, 'size' => $size, 'tables' => count($counts), 'rows' => array_sum($counts), 'files' => count($files), 'label' => $label,
            ]);

            return ['name' => $name, 'size' => $size, 'tables' => count($counts), 'rows' => array_sum($counts), 'files' => count($files), 'pruned' => $pruned];
        } catch (Throwable $exception) {
            $this->store->writeStatus(['last_error' => $exception->getMessage(), 'last_error_at' => now()->toIso8601String()]);
            throw $exception;
        } finally {
            BackupStore::removeDirectory($work);
        }
    }
}
