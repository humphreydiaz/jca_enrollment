<?php

namespace App\Modules\Grading\Support;

use App\Modules\Academics\Models\GradeLevel;

/** Grades 1-10 use numeric four-quarter grades (D-038); Nursery and Kinder do not. */
final class NumericGradeLevels
{
    public const CODE_PATTERN = '/^G(?:[1-9]|10)$/';

    public static function includes(GradeLevel|string|null $grade): bool
    {
        $code = $grade instanceof GradeLevel ? $grade->code : $grade;

        return is_string($code) && preg_match(self::CODE_PATTERN, $code) === 1;
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_map(fn (int $number): string => 'G'.$number, range(1, 10));
    }
}
