<?php

namespace App\Modules\Enrollment\Actions;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveEnrollmentDraftAction
{
    public function __construct(
        private readonly AuditRecorder $audit
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(
        Enrollment $enrollment,
        array $data,
        ?User $actor = null
    ): Enrollment {
        return DB::transaction(function () use ($enrollment, $data, $actor): Enrollment {

            $record = Enrollment::query()
                ->lockForUpdate()
                ->findOrFail($enrollment->id);

            if ($record->status !== 'draft') {
                throw ValidationException::withMessages([
                    'application' => 'Only draft applications can be edited.',
                ]);
            }

            $record->loadMissing('academicYear');
            $year = $record->academicYear;
            $today = now('Asia/Manila')->toDateString();

            if (
                ! $year ||
                $year->status !== 'open' ||
                ! $year->enrollment_is_open
            ) {
                throw ValidationException::withMessages([
                    'application' => 'Enrollment is currently closed.',
                ]);
            }

            if (
                $year->enrollment_opens_on &&
                $today < $year->enrollment_opens_on->toDateString()
            ) {
                throw ValidationException::withMessages([
                    'application' => 'Enrollment is not yet open.',
                ]);
            }

            if (
                $year->enrollment_closes_on &&
                $today > $year->enrollment_closes_on->toDateString()
            ) {
                throw ValidationException::withMessages([
                    'application' => 'Enrollment is closed. The enrollment deadline has passed.',
                ]);
            }

            $student = StudentProfile::query()
                ->lockForUpdate()
                ->findOrFail($record->student_id);

            $fieldMap = [
                'lrn' => 'lrn',
                'student_middle_name' => 'middle_name',
                'birth_place' => 'birth_place',
                'mother_tongue' => 'mother_tongue',
                'religion' => 'religion',
                'is_4ps_recipient' => 'is_4ps_recipient',
                'four_ps_household_id' => 'four_ps_household_id',
                'is_learner_with_disability' => 'is_learner_with_disability',
                'disability_type' => 'disability_type',
                'current_address' => 'current_address',
                'permanent_address' => 'permanent_address',
            ];

            $studentData = [];

            foreach ($fieldMap as $input => $column) {
                if (array_key_exists($input, $data)) {
                    $studentData[$column] = $data[$input];
                }
            }

            if (
                ! empty($data['same_as_current_address']) &&
                ! empty($data['current_address'])
            ) {
                $studentData['permanent_address'] = $data['current_address'];
            }

            if ($studentData !== []) {
                $student->forceFill($studentData)->save();
            }

            $recordData = [];

            foreach ([
                'guardian_relationship',
                'student_category',
                'previous_school_name',
                'previous_school_address',
            ] as $field) {
                if (array_key_exists($field, $data)) {
                    $recordData[$field] = $data[$field];
                }
            }

            if ($recordData !== []) {
                $record->forceFill($recordData)->save();
            }

            $this->audit->record(
                $actor,
                'enrollment.draft.saved',
                $record
            );

            return $record->fresh();
        });
    }
}
