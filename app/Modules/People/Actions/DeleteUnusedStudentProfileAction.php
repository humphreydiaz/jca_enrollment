<?php

namespace App\Modules\People\Actions;

use App\Models\User;
use App\Modules\Communications\Models\AnnouncementAudience;
use App\Modules\Enrollment\Actions\ClearMistakenStudentDuplicateReferencesAction;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteUnusedStudentProfileAction
{
    public function __construct(
        private readonly ClearMistakenStudentDuplicateReferencesAction $clearDuplicateReferences,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(StudentProfile $student, User $actor, string $reason): void
    {
        DB::transaction(function () use ($student, $actor, $reason): void {
            $record = StudentProfile::query()->lockForUpdate()->findOrFail($student->id);

            if ($record->status !== 'active') {
                throw ValidationException::withMessages(['student' => 'Only an active profile created by mistake can be removed. Historical duplicate or inactive profiles must be preserved.']);
            }

            if ($record->user_id !== null) {
                throw ValidationException::withMessages(['student' => 'Disconnect the Student login before removing this profile.']);
            }

            if ($record->enrollments()->exists()) {
                throw ValidationException::withMessages(['student' => 'This profile has enrollment history and must be preserved.']);
            }

            if ($record->guardianLinks()->exists()) {
                throw ValidationException::withMessages(['student' => 'This profile has guardian relationship history and must be preserved.']);
            }

            if (AnnouncementAudience::query()->where('student_id', $record->id)->exists()) {
                throw ValidationException::withMessages(['student' => 'This profile has communication history and must be preserved.']);
            }

            $clearedReferences = $this->clearDuplicateReferences->execute($record, $actor, $reason);

            $this->audit->record($actor, 'people.student.mistaken_profile_deleted', $record, [
                'duplicate_references_cleared' => $clearedReferences,
                'reason' => $reason,
            ]);

            $record->delete();
        });
    }
}
