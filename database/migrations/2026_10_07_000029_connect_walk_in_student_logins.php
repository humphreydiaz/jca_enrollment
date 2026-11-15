<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D-048 data repair: Grade 7–10 walk-in access created a Student login for the learner but
 * never connected it to the learner record, so after enrollment the student's grades, finance,
 * and schedule pages were empty. Connect each Registrar-verified walk-in login to its learner,
 * only when neither side is already connected elsewhere. No other data is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('enrollments')
            ->join('student_profiles', 'student_profiles.id', '=', 'enrollments.student_id')
            ->whereNotNull('enrollments.walk_in_verified_at')
            ->whereNotNull('enrollments.student_applicant_user_id')
            ->whereNull('student_profiles.user_id')
            ->orderBy('enrollments.id')
            ->get(['student_profiles.id as student_id', 'enrollments.student_applicant_user_id as user_id']);

        foreach ($rows as $row) {
            $alreadyUsed = DB::table('student_profiles')->where('user_id', $row->user_id)->exists();
            if ($alreadyUsed) {
                continue;
            }
            DB::table('student_profiles')->where('id', $row->student_id)->whereNull('user_id')
                ->update(['user_id' => $row->user_id, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: the connections are correct and other records may now depend on them.
    }
};
