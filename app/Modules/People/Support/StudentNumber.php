<?php

namespace App\Modules\People\Support;

use App\Modules\Academics\Models\AcademicYear;

/**
 * The school's student-number scheme: "S-2026-2027-0011" — an "S", the entry
 * school year, and a four-digit sequence within that year. This class owns the
 * format so issuance, login resolution, and validation all agree on it.
 */
class StudentNumber
{
    /** Matches a complete student number, case-insensitively. */
    public const PATTERN = '/^S-\d{4}-\d{4}-\d{4}$/i';

    public static function looksLike(string $value): bool
    {
        return (bool) preg_match(self::PATTERN, trim($value));
    }

    /** Canonical form used for storage and lookup (uppercase, trimmed). */
    public static function normalize(string $value): string
    {
        return strtoupper(trim($value));
    }

    public static function format(string $yearPrefix, int $sequence): string
    {
        return sprintf('S-%s-%04d', $yearPrefix, $sequence);
    }

    /**
     * The entry-year prefix ("2026-2027") derived from an academic year. Falls
     * back to the year code when the calendar dates are not set.
     */
    public static function yearPrefix(AcademicYear $year): string
    {
        $start = $year->starts_on?->year;
        $end = $year->ends_on?->year;

        if ($start !== null && $end !== null) {
            return $start.'-'.$end;
        }

        // "School Year 2026-2027" or "2026-2027" → "2026-2027".
        if (preg_match('/(\d{4})\D+(\d{4})/', (string) $year->name.' '.(string) $year->code, $matches) === 1) {
            return $matches[1].'-'.$matches[2];
        }

        $fallbackStart = $start ?? (int) date('Y');

        return $fallbackStart.'-'.($fallbackStart + 1);
    }
}
