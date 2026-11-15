<?php

namespace App\Modules\Grading\Support;

use App\Modules\Grading\Models\StudentSubjectGrade;

/**
 * Hints that a submitted grade deserves a second look before approval. These are review
 * aids only: they never block approval and are not a passing mark (that stays pending
 * under P-010).
 */
final class GradeReviewFlags
{
    public const LOW_QUARTER = 60;

    public const QUARTER_SPREAD = 20;

    /** @return list<string> */
    public static function for(StudentSubjectGrade $grade): array
    {
        $quarters = array_values(array_filter(
            [$grade->quarter_1, $grade->quarter_2, $grade->quarter_3, $grade->quarter_4],
            fn ($value): bool => $value !== null,
        ));
        if ($quarters === []) {
            return [];
        }

        $flags = [];
        if (min($quarters) < self::LOW_QUARTER) {
            $flags[] = 'A quarter is below '.self::LOW_QUARTER;
        }
        if (max($quarters) - min($quarters) >= self::QUARTER_SPREAD) {
            $flags[] = 'Quarters differ by '.self::QUARTER_SPREAD.'+ points';
        }
        if ($grade->final_grade === 100) {
            $flags[] = 'Final grade of 100';
        }

        return $flags;
    }
}
