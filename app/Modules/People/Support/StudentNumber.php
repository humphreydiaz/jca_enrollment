<?php

namespace App\Modules\People\Support;

use App\Modules\Academics\Models\AcademicYear;

/** Owns the JCA student-number format used by issuance and login lookup. */
class StudentNumber
{
    public const PATTERN = '/^JCA-\d{2}-\d{3}$/i';

    public static function looksLike(string $value): bool
    {
        return (bool) preg_match(self::PATTERN, trim($value));
    }

    /** Canonical form used for storage and lookup (uppercase, trimmed). */
    public static function normalize(string $value): string
    {
        return strtoupper(trim($value));
    }

    public static function format(string $yearCode, string $suffix): string
    {
        return sprintf('JCA-%s-%s', $yearCode, $suffix);
    }

    /**
     * The entry-year prefix ("2026-2027") derived from an academic year. Falls
     * back to the year code when the calendar dates are not set.
     */
    public static function yearCode(AcademicYear $year): string
    {
        $start = $year->starts_on?->year;

        if ($start !== null) {
            return substr((string) $start, -2);
        }

        if (preg_match('/(\d{4})/', (string) $year->name.' '.(string) $year->code, $matches) === 1) {
            return substr($matches[1], -2);
        }

        return now()->format('y');
    }
}
