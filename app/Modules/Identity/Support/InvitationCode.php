<?php

namespace App\Modules\Identity\Support;

final class InvitationCode
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function generate(): string
    {
        $code = '';

        for ($index = 0; $index < 16; $index++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return self::format($code);
    }

    public static function normalize(string $code): string
    {
        $compact = strtoupper((string) preg_replace('/[\s-]+/', '', trim($code)));

        return self::format($compact);
    }

    private static function format(string $code): string
    {
        return implode('-', str_split($code, 4));
    }
}
