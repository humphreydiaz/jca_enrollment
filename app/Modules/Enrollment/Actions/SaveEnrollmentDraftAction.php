<?php

namespace App\Modules\Enrollment\Actions;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveEnrollmentDraftAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /** @param array<string, mixed> $data */
    public function execute(Enrollment $enrollment, array $data): Enrollment
    {
        if ($enrollment->status !== 'draft') {
            throw ValidationException::withMessages(['application' => 'Only draft applications can be edited.']);
        }

        return DB::transaction(function () use ($enrollment, $data): Enrollment {
            $record = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            $student = StudentProfile::query()->lockForUpdate()->findOrFail($record->student_id);
            $student->update(['middle_name' => $data['student_middle_name'] ?? null]);
            $record->update([
                'guardian_relationship' => $data['guardian_relationship'],
                'previous_school_name' => $data['previous_school_name'] ?? null,
            ]);
            $this->audit->record(null, 'enrollment.draft.saved', $record);

            return $record;
        });
    }
}
