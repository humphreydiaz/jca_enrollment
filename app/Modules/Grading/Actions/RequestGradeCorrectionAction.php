<?php

namespace App\Modules\Grading\Actions;

use App\Models\User;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Grading\Models\GradeCorrectionRequest;
use App\Modules\Grading\Models\GradeRevision;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RequestGradeCorrectionAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(StudentSubjectGrade $grade, TeachingAssignment $assignment, User $actor, string $reason): GradeCorrectionRequest
    {
        Gate::forUser($actor)->authorize('update', [$grade, $assignment]);

        return DB::transaction(function () use ($grade, $assignment, $actor, $reason): GradeCorrectionRequest {
            $grade = StudentSubjectGrade::query()->lockForUpdate()->findOrFail($grade->id);
            Gate::forUser($actor)->authorize('update', [$grade, $assignment]);
            if (! in_array($grade->status, ['submitted', 'finalized'], true)
                || $grade->corrections()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['grade' => 'Only a submitted or finalized result without a pending request may be reopened.']);
            }
            $request = GradeCorrectionRequest::query()->create([
                'student_subject_grade_id' => $grade->id, 'requested_by' => $actor->id,
                'previous_status' => $grade->status, 'reason' => $reason, 'status' => 'pending',
            ]);
            GradeRevision::query()->create(['student_subject_grade_id' => $grade->id, 'event_type' => 'reopen_requested', 'actor_user_id' => $actor->id, 'reason' => $reason, 'occurred_at' => now()]);
            $this->audit->record($actor, 'grading.reopen_requested', $grade, ['request_public_id' => $request->public_id]);

            return $request;
        });
    }
}
