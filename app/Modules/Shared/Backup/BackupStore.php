<?php

namespace App\Modules\Shared\Backup;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * D-060: the folder holding encrypted backups (default storage/app/backups, which the web server never serves),
 * the last-run status, and retention.
 */
final class BackupStore
{
    public const PATTERN = '/^jca-backup-\d{8}-\d{6}(?:-[a-z0-9-]{1,40})?\.jcabak$/';

    public function directory(): string
    {
        $directory = rtrim((string) config('operations.backup.path'), '/\\');
        if (! is_dir($directory) && ! @mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException("Cannot create the backup folder {$directory}.");
        }
        // Defense in depth: even if the folder ends up under a served path, Apache refuses to serve it.
        if (! is_file($directory.'/.htaccess')) {
            file_put_contents($directory.'/.htaccess', "Require all denied\nDeny from all\n");
        }

        return $directory;
    }

    public function path(string $name): string
    {
        if (preg_match(self::PATTERN, $name) !== 1) {
            throw new RuntimeException('That is not a JCA backup file name.');
        }

        return $this->directory().DIRECTORY_SEPARATOR.$name;
    }

    /** Accepts a bare backup name from the backup folder or a full path to a file anywhere. */
    public function resolve(string $file): string
    {
        if (preg_match(self::PATTERN, basename($file)) === 1 && basename($file) === $file) {
            return $this->path($file);
        }

        return $file;
    }

    public function newName(?string $label = null): string
    {
        $label = $label !== null ? trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($label)), '-') : '';

        return 'jca-backup-'.now()->format('Ymd-His').($label !== '' ? '-'.substr($label, 0, 40) : '').'.jcabak';
    }

    /** @return Collection<int, array{name: string, size: int, created_at: Carbon}> newest first */
    public function all(): Collection
    {
        return collect(glob($this->directory().DIRECTORY_SEPARATOR.'jca-backup-*.jcabak') ?: [])
            ->filter(fn (string $path) => preg_match(self::PATTERN, basename($path)) === 1)
            ->map(fn (string $path) => ['name' => basename($path), 'size' => (int) filesize($path), 'created_at' => Carbon::createFromTimestamp((int) filemtime($path))])
            ->sortByDesc(fn (array $backup) => $backup['name'])->values();
    }

    /** Keeps the newest backups (BACKUP_KEEP, default 14) and deletes older ones. @return list<string> deleted names */
    public function prune(): array
    {
        $deleted = [];
        foreach ($this->all()->slice((int) config('operations.backup.keep')) as $backup) {
            @unlink($this->path($backup['name']));
            $deleted[] = $backup['name'];
        }

        return $deleted;
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $file = $this->directory().DIRECTORY_SEPARATOR.'status.json';
        $status = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($status) ? $status : [];
    }

    /** @param array<string, mixed> $changes */
    public function writeStatus(array $changes): void
    {
        file_put_contents($this->directory().DIRECTORY_SEPARATOR.'status.json', json_encode([...$this->status(), ...$changes], JSON_PRETTY_PRINT));
    }

    public function temporaryDirectory(): string
    {
        $directory = $this->directory().DIRECTORY_SEPARATOR.'tmp-'.bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);

        return $directory;
    }

    public static function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
