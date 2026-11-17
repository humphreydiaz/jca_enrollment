<?php

namespace Database\Seeders\Capstone;

use App\Modules\People\Support\RandomThreeDigitSuffix;

/**
 * Hands out the next pinned Student/Staff ID suffix (the IDs the hosted capstone site issued), so the
 * normal issuing actions record the same numbers on every rebuild. Falls back to random when none is queued.
 */
final class PinnedThreeDigitSuffix extends RandomThreeDigitSuffix
{
    private ?string $next = null;

    public function pin(?string $identifier): void
    {
        $this->next = $identifier !== null && preg_match('/(\d{3})$/', $identifier, $match) === 1 ? $match[1] : null;
    }

    public function generate(): string
    {
        $suffix = $this->next ?? parent::generate();
        $this->next = null;

        return $suffix;
    }
}
