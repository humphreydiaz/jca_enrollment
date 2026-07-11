<?php

namespace App\Modules\Enrollment\Actions;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewEnrollmentDuplicateAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Enrollment $enrollment, string $decision, User $actor, string $reason, ?StudentProfile $student = null, ?GuardianProfile $guardian = null): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $decision, $actor, $reason, $student, $guardian): Enrollment {
            $record = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            if ($record->status !== 'draft' && $record->status !== 'submitted') {
                throw ValidationException::withMessages(['duplicate_decision' => 'Duplicate review is closed after formal review begins.']);
            }
            if ($decision === 'link_existing') {
                if ($student === null && $guardian === null) {
                    throw ValidationException::withMessages(['student_public_id' => 'Select a confirmed existing student or guardian.']);
                }
                if ($student !== null) {
                    if (Enrollment::query()->where('student_id', $student->id)->where('academic_year_id', $record->academic_year_id)->whereNotIn('status', ['rejected', 'withdrawn', 'cancelled'])->whereKeyNot($record->id)->exists()) {
                        throw ValidationException::withMessages(['student_public_id' => 'That student already has an active application for this school year.']);
                    }
                    $draftStudent = StudentProfile::query()->lockForUpdate()->findOrFail($record->student_id);
                    $draftStudent->update(['status' => 'duplicate']);
                    $record->student_id = $student->id;
                    $record->active_enrollment_key = $student->id.':'.$record->academic_year_id;
                    $record->potential_duplicate_student_id = null;
                }
                if ($guardian !== null) {
                    $draftGuardian = GuardianProfile::query()->lockForUpdate()->findOrFail($record->applicant_guardian_id);
                    $draftGuardian->update(['status' => 'duplicate']);
                    $record->applicant_guardian_id = $guardian->id;
                    $record->potential_duplicate_guardian_id = null;
                }
            }
            if ($decision === 'clear') {
                $record->potential_duplicate_student_id = null;
                $record->potential_duplicate_guardian_id = null;
            }
            $reviewStatus = $record->potential_duplicate_student_id || $record->potential_duplicate_guardian_id ? 'pending' : ($decision === 'clear' ? 'clear' : 'linked');
            $record->fill([
                'duplicate_review_status' => $reviewStatus,
                'duplicate_reviewed_by' => $actor->id,
                'duplicate_reviewed_at' => now(),
                'duplicate_review_reason' => $reason,
            ])->save();
            $this->audit->record($actor, 'enrollment.duplicate.reviewed', $record, ['decision' => $decision]);

            return $record;
        });
    }
}
