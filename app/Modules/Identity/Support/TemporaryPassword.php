<?php

namespace App\Modules\Identity\Support;

final class TemporaryPassword
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

    public static function generate(): string
    {
        $random = '';

        for ($index = 0; $index < 11; $index++) {
            $random .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return 'Jca!7'.$random;
    }
}
