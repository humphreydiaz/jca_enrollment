<?php

namespace App\Modules\Shared\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

class RedactsSensitiveLogContext
{
    /**
     * @var list<string>
     */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'address',
        'api_key',
        'assessment',
        'authorization',
        'birth',
        'cookie',
        'document',
        'email',
        'file',
        'mobile',
        'password',
        'phone',
        'secret',
        'support',
        'therapy',
        'token',
        'upload',
    ];

    /**
     * Attach redaction before the configured file logger writes application context.
     */
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(static function (LogRecord $record): LogRecord {
            return $record->with(
                context: self::redact($record->context),
                extra: self::redact($record->extra),
            );
        });
    }

    /**
     * @param  array<string|int, mixed>  $context
     * @return array<string|int, mixed>
     */
    public static function redact(array $context): array
    {
        $redacted = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            $redacted[$key] = is_array($value) ? self::redact($value) : $value;
        }

        return $redacted;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = str_replace('-', '_', strtolower($key));

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
