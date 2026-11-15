<?php

namespace App\Modules\Academics\Support;

use App\Modules\Academics\Models\AcademicYear;
use Illuminate\Support\Collection;

/**
 * Picks the school year Academics screens open on: the year whose dates include today,
 * otherwise the newest open year, otherwise the newest year by start date.
 */
class CurrentAcademicYear
{
    /** @param Collection<int, AcademicYear> $years */
    public function pick(Collection $years): ?AcademicYear
    {
        $today = today();
        $sorted = $years->sortByDesc(fn (AcademicYear $year) => $year->starts_on->format('Y-m-d').'-'.$year->id)->values();

        return $sorted->first(fn (AcademicYear $year): bool => $year->status !== 'archived'
                && $year->starts_on->lte($today) && $year->ends_on->gte($today))
            ?? $sorted->firstWhere('status', 'open')
            ?? $sorted->first();
    }

    /** @param Collection<int, AcademicYear> $years */
    public function resolve(Collection $years, ?string $publicId): ?AcademicYear
    {
        return ($publicId !== null ? $years->firstWhere('public_id', $publicId) : null) ?? $this->pick($years);
    }
}
