<?php

namespace App\Modules\Enrollment\Actions;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClearMistakenStudentDuplicateReferencesAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(StudentProfile $candidate, User $actor, string $reason): int
    {
        return DB::transaction(function () use ($candidate, $actor, $reason): int {
            $enrollments = Enrollment::query()
                ->where('potential_duplicate_student_id', $candidate->id)
                ->lockForUpdate()
                ->get();

            foreach ($enrollments as $enrollment) {
                if (! in_array($enrollment->status, ['draft', 'submitted'], true)
                    || $enrollment->duplicate_review_status !== 'pending') {
                    throw ValidationException::withMessages([
                        'student' => 'This profile is part of a closed or completed duplicate review and cannot be removed.',
                    ]);
                }

                $enrollment->forceFill([
                    'potential_duplicate_student_id' => null,
                    'duplicate_review_status' => $enrollment->potential_duplicate_guardian_id === null ? 'clear' : 'pending',
                    'duplicate_reviewed_by' => $actor->id,
                    'duplicate_reviewed_at' => now(),
                    'duplicate_review_reason' => $reason,
                ])->save();

                $this->audit->record($actor, 'enrollment.duplicate.mistaken_candidate_removed', $enrollment, [
                    'candidate_profile_public_id' => $candidate->public_id,
                ]);
            }

            return $enrollments->count();
        });
    }
}
