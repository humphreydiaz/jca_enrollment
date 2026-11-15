<?php

namespace App\Modules\Shared\Backup;

use App\Models\User;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * D-060: restores an encrypted backup made by CreateBackupAction.
 *
 * Everything is decrypted and checked against the manifest before anything is touched. A drill restore loads the
 * backup into a separate empty database and folder, leaving the live system alone. An in-place restore first takes
 * a safety backup of the current state, then replaces every table and moves the current private documents aside
 * (storage/app/restore-previous-...) before unpacking the backed-up documents; nothing is deleted.
 */
class RestoreBackupAction
{
    public function __construct(
        private readonly BackupStore $store,
        private readonly BackupCipher $cipher,
        private readonly SqlImporter $importer,
        private readonly CreateBackupAction $createBackup,
        private readonly AuditRecorder $audit,
    ) {}

    /** @return array<string, mixed> manifest of the decrypted backup, after verifying every checksum */
    public function inspect(string $file): array
    {
        $work = $this->store->temporaryDirectory();
        try {
            return $this->open($file, $work)['manifest'];
        } finally {
            BackupStore::removeDirectory($work);
        }
    }

    /**
     * Restores over the live system, from the command line or Admin → Backups: verifies the file and key, records
     * the request, takes a safety backup, and restores in maintenance mode. If the restore fails part-way, the safety
     * backup is put back automatically; only if that also fails does the site stay in maintenance mode.
     *
     * @return array{manifest: array<string, mixed>, mode: string, statements: int, files: int, row_mismatches: array<string, array{expected: int, actual: int}>, safety_backup: ?string, previous_files: ?string, files_path: string}
     *
     * @throws RestoreFailedException
     */
    public function restoreLive(string $file, ?User $actor, string $via, bool $allowDifferentAppKey = false): array
    {
        $lock = fopen($this->store->directory().DIRECTORY_SEPARATOR.'restore.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RestoreFailedException('Another restore is already running. Wait for it to finish.', false);
        }

        $stayDown = false;
        $wentDown = false;
        try {
            $manifest = $this->inspect($file);
            $this->assertSameAppKey($manifest, $allowDifferentAppKey);
            $this->audit->record($actor, 'operations.backup.restore_started', null, ['name' => basename($file), 'via' => $via]);
            $safety = $this->createBackup->execute($actor, 'pre-restore')['name'];

            Artisan::call('down', ['--retry' => 60]);
            $wentDown = true;
            try {
                return [...$this->execute($file, ['actor' => $actor, 'via' => $via, 'allow_different_app_key' => $allowDifferentAppKey]), 'safety_backup' => $safety];
            } catch (Throwable $failure) {
                try {
                    $this->execute($safety, ['actor' => $actor, 'via' => $via.' (automatic rollback)']);
                } catch (Throwable $rollback) {
                    $stayDown = true;
                    throw new RestoreFailedException('The restore failed and the automatic rollback also failed, so the site stays in maintenance mode. '
                        ."From SSH run: php artisan jca:restore {$safety} --force, then php artisan up. Cause: {$failure->getMessage()} / {$rollback->getMessage()}", true, $failure);
                }
                throw new RestoreFailedException("The restore failed, so the system was put back exactly as it was just before (from {$safety}). Nothing was lost. Cause: {$failure->getMessage()}", false, $failure);
            }
        } catch (RestoreFailedException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RestoreFailedException('The restore was not started: '.$exception->getMessage(), false, $exception);
        } finally {
            if ($wentDown) {
                Artisan::call('optimize:clear');
                if (! $stayDown) {
                    Artisan::call('up');
                }
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * @param  array{drill_database?: ?string, drill_files?: ?string, allow_different_app_key?: bool, actor?: ?User, via?: string}  $options
     * @return array{manifest: array<string, mixed>, mode: string, statements: int, files: int, row_mismatches: array<string, array{expected: int, actual: int}>, safety_backup: ?string, previous_files: ?string, files_path: string}
     */
    public function execute(string $file, array $options = []): array
    {
        $drillDatabase = $options['drill_database'] ?? null;
        $work = $this->store->temporaryDirectory();
        try {
            ['manifest' => $manifest, 'zip' => $zipPath] = $this->open($file, $work);
            $this->assertSameAppKey($manifest, (bool) ($options['allow_different_app_key'] ?? false));

            $zip = new ZipArchive;
            $zip->open($zipPath);
            $sqlPath = $work.'/database.sql';
            file_put_contents($sqlPath, $zip->getFromName('database.sql'));

            $previous = null;
            if ($drillDatabase !== null) {
                $pdo = $this->drillConnection($drillDatabase);
                $filesPath = $options['drill_files'] ?? storage_path('app/restore-drill-'.now()->format('Ymd-His'));
                if (is_dir($filesPath) && (new \FilesystemIterator($filesPath))->valid()) {
                    throw new RuntimeException("The drill folder {$filesPath} is not empty.");
                }
            } else {
                $filesPath = rtrim((string) config('filesystems.disks.local.root'), '/\\');
                if (is_dir($filesPath)) {
                    $previous = dirname($filesPath).DIRECTORY_SEPARATOR.'restore-previous-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(2));
                    if (! @rename($filesPath, $previous)) {
                        throw new RuntimeException("Cannot move the current documents aside to {$previous}; nothing was restored.");
                    }
                }
                $pdo = DB::connection()->getPdo();
                $this->importer->dropAllTables($pdo);
            }

            $statements = $this->importer->import($pdo, $sqlPath);
            $restoredFiles = $this->extractFiles($zip, $filesPath);
            $zip->close();
            if ($previous !== null && is_file($previous.'/.gitignore')) {
                copy($previous.'/.gitignore', $filesPath.'/.gitignore');
            }

            $mismatches = [];
            foreach (($manifest['database']['rows'] ?? []) as $table => $expected) {
                $actual = (int) $pdo->query('SELECT COUNT(*) FROM `'.str_replace('`', '``', $table).'`')->fetchColumn();
                if ($actual !== (int) $expected) {
                    $mismatches[$table] = ['expected' => (int) $expected, 'actual' => $actual];
                }
            }

            if ($drillDatabase === null) {
                DB::purge();
                // The restored users table may not contain the person who restored (an older backup), so the
                // actor is linked only when that account exists; the name is kept either way.
                $actor = $options['actor'] ?? null;
                $this->audit->record($actor !== null && User::query()->whereKey($actor->id)->exists() ? $actor : null, 'operations.backup.restored', null, [
                    'name' => basename($file), 'backup_created_at' => $manifest['created_at'] ?? null,
                    'via' => $options['via'] ?? 'command line', 'restored_by' => $actor?->name,
                ]);
            }

            return [
                'manifest' => $manifest, 'mode' => $drillDatabase === null ? 'in-place' : 'drill', 'statements' => $statements,
                'files' => $restoredFiles, 'row_mismatches' => $mismatches, 'safety_backup' => null, 'previous_files' => $previous, 'files_path' => $filesPath,
            ];
        } finally {
            BackupStore::removeDirectory($work);
        }
    }

    /** @param array<string, mixed> $manifest */
    private function assertSameAppKey(array $manifest, bool $allowDifferent): void
    {
        if (($manifest['app_key_fingerprint'] ?? null) !== substr(hash('sha256', (string) config('app.key')), 0, 16) && ! $allowDifferent) {
            throw new RuntimeException('This backup was made with a different APP_KEY, so encrypted fields would be unreadable. Put the original APP_KEY back in .env first (or, from SSH only, pass --allow-different-app-key if you accept that).');
        }
    }

    /** @return array{manifest: array<string, mixed>, zip: string} */
    private function open(string $file, string $work): array
    {
        $path = $this->store->resolve($file);
        if (! is_file($path)) {
            throw new RuntimeException("Backup file not found: {$file}");
        }
        $zipPath = $work.'/backup.zip';
        $this->cipher->decrypt($path, $zipPath);

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('The decrypted backup is not a readable archive.');
        }
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        if (! is_array($manifest) || ($manifest['format'] ?? null) !== 1) {
            throw new RuntimeException('The backup manifest is missing or from an unknown version.');
        }
        $sql = $zip->getFromName('database.sql');
        if ($sql === false || hash('sha256', $sql) !== ($manifest['database']['sha256'] ?? null)) {
            throw new RuntimeException('The database part of the backup does not match its checksum.');
        }
        foreach (($manifest['files'] ?? []) as $relative => $meta) {
            $contents = $zip->getFromName('files/'.$relative);
            if ($contents === false || hash('sha256', $contents) !== ($meta['sha256'] ?? null)) {
                throw new RuntimeException("The document {$relative} in the backup does not match its checksum.");
            }
        }
        $zip->close();

        return ['manifest' => $manifest, 'zip' => $zipPath];
    }

    private function extractFiles(ZipArchive $zip, string $target): int
    {
        if (! is_dir($target)) {
            mkdir($target, 0750, true);
        }
        $count = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            if (! str_starts_with($name, 'files/') || str_ends_with($name, '/')) {
                continue;
            }
            $relative = substr($name, 6);
            if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/') || str_contains($relative, ':')) {
                throw new RuntimeException("Refusing to restore an unsafe path: {$relative}");
            }
            $destination = $target.'/'.$relative;
            if (! is_dir(dirname($destination))) {
                mkdir(dirname($destination), 0750, true);
            }
            file_put_contents($destination, $zip->getFromIndex($index));
            $count++;
        }

        return $count;
    }

    /** An empty database on the same server, for restore drills; never the live database. */
    private function drillConnection(string $database): PDO
    {
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $database) !== 1) {
            throw new RuntimeException('The drill database name may contain only letters, numbers, and underscores.');
        }
        $config = DB::connection()->getConfig();
        if ($database === ($config['database'] ?? null)) {
            throw new RuntimeException('The drill database must not be the live database.');
        }
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['host'], $config['port']);
        $pdo = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\PDOException) {
            // Shared hosting: create the database in hPanel first and give this database user access to it.
        }
        $pdo->exec("USE `{$database}`");
        if ((new DatabaseDumper)->tables($pdo) !== []) {
            throw new RuntimeException("The drill database {$database} is not empty.");
        }

        return $pdo;
    }
}
