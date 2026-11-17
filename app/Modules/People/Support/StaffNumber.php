<?php

namespace App\Modules\People\Support;

use Carbon\CarbonInterface;

class StaffNumber
{
    public const PATTERN = '/^\d{2}-JCA-\d{3}$/i';

    public static function looksLike(string $value): bool
    {
        return (bool) preg_match(self::PATTERN, trim($value));
    }

    public static function normalize(string $value): string
    {
        return strtoupper(trim($value));
    }

    public static function format(string $yearCode, string $suffix): string
    {
        return sprintf('%s-JCA-%s', $yearCode, $suffix);
    }

    public static function yearCode(CarbonInterface $employedOn): string
    {
        return $employedOn->format('y');
    }
}
