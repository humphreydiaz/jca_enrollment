<?php

namespace App\Modules\Grading\Actions;

use App\Models\User;
use App\Modules\Grading\Models\GradeCorrectionRequest;
use App\Modules\Grading\Models\GradeRevision;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReviewGradeCorrectionAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(GradeCorrectionRequest $request, User $actor, string $decision, string $note): void
    {
        Gate::forUser($actor)->authorize('finalize', $request->grade);
        DB::transaction(function () use ($request, $actor, $decision, $note): void {
            $request = GradeCorrectionRequest::query()->lockForUpdate()->findOrFail($request->id);
            $grade = StudentSubjectGrade::query()->lockForUpdate()->findOrFail($request->student_subject_grade_id);
            if ($request->status !== 'pending' || $grade->status !== $request->previous_status) {
                throw ValidationException::withMessages(['grade' => 'This correction request is no longer pending for the recorded grade state.']);
            }
            $request->update(['status' => $decision === 'approve' ? 'approved' : 'rejected', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_note' => $note]);
            if ($decision === 'approve') {
                $grade->update(['status' => 'draft', 'submitted_by' => null, 'submitted_at' => null, 'finalized_by' => null, 'finalized_at' => null]);
            }
            GradeRevision::query()->create(['student_subject_grade_id' => $grade->id, 'event_type' => $decision === 'approve' ? 'reopened' : 'reopen_rejected', 'actor_user_id' => $actor->id, 'reason' => $note, 'occurred_at' => now()]);
            $this->audit->record($actor, $decision === 'approve' ? 'grading.reopened' : 'grading.reopen_rejected', $grade, ['request_public_id' => $request->public_id]);
        });
    }
}
