<?php

namespace App\Modules\Shared\Audit;

/**
 * D-059: old/new values in the audit log show enough to prove what changed without copying full personal
 * contact details into the log. "+639171234567" becomes "+63917•••4567"; "juan.cruz@gmail.com" becomes "j•••@gmail.com".
 */
final class AuditValueMask
{
    public const MOBILE = 'mobile';

    public const EMAIL = 'email';

    /** Shown as recorded (statuses, flags, codes); never use for personal data. */
    public const PLAIN = 'plain';

    public static function apply(string $mask, mixed $value): string|int|float|bool|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        return match ($mask) {
            self::MOBILE => self::mobile((string) $value),
            self::EMAIL => self::email((string) $value),
            default => is_scalar($value) ? $value : (string) json_encode($value),
        };
    }

    private static function mobile(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';
        if (strlen($digits) < 7) {
            return '•••';
        }
        $prefix = str_starts_with($value, '+') ? '+'.substr($digits, 0, 5) : substr($digits, 0, 4);

        return $prefix.'•••'.substr($digits, -4);
    }

    private static function email(string $value): string
    {
        [$local, $domain] = array_pad(explode('@', $value, 2), 2, null);

        return mb_substr((string) $local, 0, 1).'•••'.($domain !== null ? '@'.$domain : '');
    }
}
