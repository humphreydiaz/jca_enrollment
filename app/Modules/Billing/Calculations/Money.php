<?php

namespace App\Modules\Billing\Calculations;

use InvalidArgumentException;

final class Money
{
    public static function cents(string|int $amount): int
    {
        $value = (string) $amount;
        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException('Money must have at most two decimal places.');
        } $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');
        [$whole,$fraction] = array_pad(explode('.', $value, 2), 2, '');
        $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        return $negative ? -$cents : $cents;
    }

    public static function decimal(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);

        return ($negative ? '-' : '').intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function sum(iterable $amounts): string
    {
        $total = 0;
        foreach ($amounts as $amount) {
            $total += self::cents((string) $amount);
        }

        return self::decimal($total);
    }
}
