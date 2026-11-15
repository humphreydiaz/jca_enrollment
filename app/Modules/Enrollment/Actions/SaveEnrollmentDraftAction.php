<?php

namespace App\Modules\Enrollment\Actions;

use App\Models\User;
use App\Modules\Communications\Actions\UpdateSmsConsentAction;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Actions\CompleteGuardianDetailsAction;
use App\Modules\People\Actions\CompleteStudentEnrollmentProfileAction;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveEnrollmentDraftAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly CompleteStudentEnrollmentProfileAction $completeStudent,
        private readonly CompleteGuardianDetailsAction $completeGuardian,
        private readonly UpdateSmsConsentAction $smsConsent,
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

            // Learner details, parents' names, learner mobile and Indigenous Peoples group (D-049/D-057).
            $this->completeStudent->execute($student, $data, $actor);

            // Parent/guardian name (locked once the Registrar verified it) and contact (D-047).
            $guardian = $record->applicant_guardian_id !== null
                ? GuardianProfile::query()->find($record->applicant_guardian_id)
                : null;
            if ($guardian !== null && $actor !== null) {
                $this->completeGuardian->execute($guardian, $student, $data, $actor);
                $guardian->refresh();
            }

            $recordData = [];
            foreach (['guardian_relationship', 'student_category', 'previous_school_name', 'previous_school_address'] as $field) {
                if (array_key_exists($field, $data)) {
                    $recordData[$field] = $data[$field];
                }
            }

            // A contact that already belongs to another parent/guardian is flagged for the Registrar's
            // duplicate review instead of being merged.
            if ($guardian !== null && ($guardian->mobile !== null || $guardian->email !== null)) {
                $other = GuardianProfile::query()
                    ->whereKeyNot($guardian->id)
                    ->where(function ($query) use ($guardian): void {
                        if ($guardian->mobile !== null) {
                            $query->orWhere('mobile', $guardian->mobile);
                        }
                        if ($guardian->email !== null) {
                            $query->orWhereRaw('LOWER(email) = ?', [strtolower($guardian->email)]);
                        }
                    })
                    ->first();
                if ($other !== null && $record->potential_duplicate_guardian_id === null) {
                    $recordData['potential_duplicate_guardian_id'] = $other->id;
                    $recordData['duplicate_review_status'] = 'pending';
                }
            }

            if ($recordData !== []) {
                $record->forceFill($recordData)->save();
            }

            // Announcement SMS opt-ins from the application (D-052). A tick needs a number to text.
            if ($guardian !== null && array_key_exists('guardian_sms_opt_in', $data)) {
                $this->smsConsent->execute($guardian, 'announcement', (bool) $data['guardian_sms_opt_in'] && $guardian->mobile !== null, $actor, 'enrollment_application');
            }
            if (array_key_exists('student_sms_opt_in', $data)) {
                $learner = $student->fresh();
                $this->smsConsent->execute($learner, 'announcement', (bool) $data['student_sms_opt_in'] && $learner->mobile !== null, $actor, 'enrollment_application');
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
