<?php

namespace App\Modules\Grading\Support;

use App\Modules\Grading\Models\StudentSubjectGrade;

/** Identifies the exact values a reviewer saw, so a grade changed after review is never approved unseen. */
final class GradeFingerprint
{
    public static function of(StudentSubjectGrade $grade): string
    {
        return hash('sha256', implode('|', [
            $grade->public_id, $grade->status,
            $grade->quarter_1, $grade->quarter_2, $grade->quarter_3, $grade->quarter_4, $grade->final_grade,
            $grade->submitted_at?->format('Y-m-d H:i:s.u'), $grade->updated_at?->format('Y-m-d H:i:s.u'),
        ]));
    }
}
