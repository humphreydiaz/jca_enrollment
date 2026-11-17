<?php

namespace App\Modules\Shared\Backup;

use RuntimeException;

/**
 * D-060: streams a backup archive through libsodium's authenticated secretstream (XChaCha20-Poly1305),
 * so a downloaded or offsite copy is unreadable without the key and any tampering is detected on restore.
 *
 * File layout: "JCABAK01" + key source ("A" = derived from APP_KEY, "B" = BACKUP_ENCRYPTION_KEY) + stream header + chunks.
 */
final class BackupCipher
{
    private const MAGIC = 'JCABAK01';

    private const CHUNK = 1048576;

    public static function assertAvailable(): void
    {
        if (! function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push')) {
            throw new RuntimeException('The PHP sodium extension is required for encrypted backups. Enable it for this PHP version (hPanel → Advanced → PHP Configuration).');
        }
    }

    public function encrypt(string $plainPath, string $encryptedPath): void
    {
        self::assertAvailable();
        [$key, $source] = $this->key();
        $in = $this->open($plainPath, 'rb');
        $out = $this->open($encryptedPath, 'wb');
        try {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
            fwrite($out, self::MAGIC.$source.$header);
            $chunk = fread($in, self::CHUNK);
            do {
                $next = feof($in) ? '' : fread($in, self::CHUNK);
                $final = $next === '' || $next === false;
                fwrite($out, sodium_crypto_secretstream_xchacha20poly1305_push($state, (string) $chunk, '',
                    $final ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE));
                $chunk = $next;
            } while (! $final);
        } finally {
            fclose($in);
            fclose($out);
            sodium_memzero($key);
        }
    }

    public function decrypt(string $encryptedPath, string $plainPath): void
    {
        self::assertAvailable();
        $in = $this->open($encryptedPath, 'rb');
        $out = $this->open($plainPath, 'wb');
        try {
            $magic = fread($in, strlen(self::MAGIC) + 1);
            if (! is_string($magic) || ! str_starts_with($magic, self::MAGIC)) {
                throw new RuntimeException('This file is not a JCA backup.');
            }
            [$key] = $this->key($magic[strlen(self::MAGIC)]);
            $header = fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull((string) $header, $key);
            sodium_memzero($key);
            $finished = false;
            while (! feof($in)) {
                $cipher = fread($in, self::CHUNK + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);
                if ($cipher === '' || $cipher === false) {
                    break;
                }
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
                if ($result === false) {
                    throw new RuntimeException('The backup could not be decrypted: wrong key, or the file is damaged or was changed.');
                }
                [$plain, $tag] = $result;
                fwrite($out, $plain);
                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    $finished = true;
                    break;
                }
            }
            if (! $finished) {
                throw new RuntimeException('The backup file is incomplete (it was cut off during copying).');
            }
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    /** @return array{0: string, 1: string} key bytes and its source letter */
    private function key(?string $source = null): array
    {
        $configured = (string) config('operations.backup.key');
        $source ??= $configured !== '' ? 'B' : 'A';
        if ($source === 'B') {
            $key = base64_decode(str_starts_with($configured, 'base64:') ? substr($configured, 7) : $configured, true);
            if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
                throw new RuntimeException('This backup was encrypted with BACKUP_ENCRYPTION_KEY; set the same base64 32-byte key in .env.');
            }

            return [$key, 'B'];
        }

        $appKey = (string) config('app.key');
        $raw = str_starts_with($appKey, 'base64:') ? base64_decode(substr($appKey, 7), true) : $appKey;
        if (! is_string($raw) || $raw === '') {
            throw new RuntimeException('APP_KEY is not set, so the backup key cannot be derived.');
        }

        return [sodium_crypto_generichash('jca-backup-v1|'.$raw, '', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES), 'A'];
    }

    /** @return resource */
    private function open(string $path, string $mode)
    {
        $handle = @fopen($path, $mode);
        if ($handle === false) {
            throw new RuntimeException("Cannot open {$path}.");
        }

        return $handle;
    }
}
