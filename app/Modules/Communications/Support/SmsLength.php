<?php

namespace App\Modules\Communications\Support;

use App\Modules\Communications\Enums\SmsDeliveryRoute;

/**
 * D-052: how many SMS parts a text takes and what it costs, mirrored by the page's live counter.
 *
 * Plain text (the GSM alphabet) fits 160 characters in one SMS and 153 per part when longer.
 * Any other character (emoji, curly quotes) switches the whole text to Unicode: 70 / 67.
 * Priority sending is 2 credits per part; standard is 1.
 */
final class SmsLength
{
    public const MAX_PARTS = 3;

    private const GSM = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà^{}\\[~]|€";

    public static function isPlain(string $text): bool
    {
        foreach (mb_str_split($text) as $character) {
            if (! str_contains(self::GSM, $character)) {
                return false;
            }
        }

        return true;
    }

    public static function parts(string $text): int
    {
        $length = mb_strlen($text);
        if ($length === 0) {
            return 0;
        }
        [$single, $multi] = self::isPlain($text) ? [160, 153] : [70, 67];

        return $length <= $single ? 1 : (int) ceil($length / $multi);
    }

    public static function route(): SmsDeliveryRoute
    {
        return SmsDeliveryRoute::tryFrom((string) config('communications.sms.message_route', 'priority')) ?? SmsDeliveryRoute::Priority;
    }

    public static function creditsPerPart(): int
    {
        return self::route() === SmsDeliveryRoute::Standard ? 1 : 2;
    }

    public static function credits(string $text, int $recipients = 1): int
    {
        return self::parts($text) * self::creditsPerPart() * $recipients;
    }
}
