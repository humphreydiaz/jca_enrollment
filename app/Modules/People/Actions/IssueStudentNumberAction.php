<?php

namespace App\Modules\People\Actions;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\People\Models\StudentProfile;
use App\Modules\People\Support\StudentNumber;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

class IssueStudentNumberAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * Issue the learner's permanent student number, keyed to their entry year.
     * Idempotent: a profile that already carries a number keeps it, so a learner
     * promoted into a later year never receives a second number.
     */
    public function execute(StudentProfile $student, AcademicYear $entryYear): string
    {
        if (filled($student->student_number)) {
            return $student->student_number;
        }

        $prefix = StudentNumber::yearPrefix($entryYear);
        $number = StudentNumber::format($prefix, $this->nextSequence($prefix));

        $student->forceFill(['student_number' => $number])->save();
        $this->audit->record(null, 'people.student.number_issued', $student, [
            'student_number' => $number,
            'year_prefix' => $prefix,
        ]);

        return $number;
    }

    /**
     * Reserve the next sequence for a year prefix under a row lock so two
     * concurrent submissions can never receive the same number.
     *
     * The counter is reconciled with the numbers already present on student
     * profiles — seed data, manual entries, or a database re-import can leave
     * the counter behind. Issuing above both the counter and the highest
     * existing number keeps issuance collision-free while never handing back a
     * number that was already used.
     */
    private function nextSequence(string $prefix): int
    {
        return DB::transaction(function () use ($prefix): int {
            DB::table('student_number_counters')->insertOrIgnore([
                'year_prefix' => $prefix,
                'last_sequence' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $current = (int) DB::table('student_number_counters')
                ->where('year_prefix', $prefix)
                ->lockForUpdate()
                ->value('last_sequence');

            $existingMax = (int) DB::table('student_profiles')
                ->where('student_number', 'like', 'S-'.$prefix.'-%')
                ->selectRaw("COALESCE(MAX(CAST(SUBSTRING_INDEX(student_number, '-', -1) AS UNSIGNED)), 0) AS mx")
                ->value('mx');

            $next = max($current, $existingMax) + 1;

            DB::table('student_number_counters')
                ->where('year_prefix', $prefix)
                ->update(['last_sequence' => $next, 'updated_at' => now()]);

            return $next;
        });
    }
}
