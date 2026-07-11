<?php

namespace App\Modules\Identity\Actions;

class ContactNormalizer
{
    public static function email(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    public static function mobile(?string $mobile): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $mobile) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = '63'.substr($digits, 1);
        }

        return "+{$digits}";
    }
}
