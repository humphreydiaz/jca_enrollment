<?php

namespace App\Modules\People\Actions;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\People\Models\StudentProfile;
use App\Modules\People\Support\RandomThreeDigitSuffix;
use App\Modules\People\Support\StudentNumber;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

class IssueStudentNumberAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly RandomThreeDigitSuffix $suffix,
    ) {}

    /**
     * Issue the learner's permanent student number, keyed to their entry year.
     * Idempotent: a profile that already carries a number keeps it, so a learner
     * promoted into a later year never receives a second number.
     */
    public function execute(StudentProfile $student, AcademicYear $entryYear): string
    {
        $yearCode = StudentNumber::yearCode($entryYear);

        return DB::transaction(function () use ($student, $yearCode): string {
            $record = StudentProfile::query()->lockForUpdate()->findOrFail($student->id);

            if (filled($record->student_number)) {
                return $record->student_number;
            }

            DB::table('identifier_namespaces')->insertOrIgnore([
                'identifier_type' => 'student',
                'year_code' => $yearCode,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('identifier_namespaces')
                ->where('identifier_type', 'student')
                ->where('year_code', $yearCode)
                ->lockForUpdate()
                ->first();

            for ($attempt = 0; $attempt < 900; $attempt++) {
                $number = StudentNumber::format($yearCode, $this->suffix->generate());

                if (! StudentProfile::query()->where('student_number', $number)->exists()) {
                    $record->forceFill(['student_number' => $number])->save();
                    $this->audit->record(null, 'people.student.number_issued', $record, [
                        'student_number' => $number,
                        'entry_year' => $yearCode,
                    ]);

                    return $number;
                }
            }

            throw new \RuntimeException("The JCA-{$yearCode} student-number namespace is exhausted.");
        });
    }
}
