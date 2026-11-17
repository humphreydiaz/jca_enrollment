<?php

namespace App\Modules\Grading\Actions;

use App\Models\User;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Grading\Models\GradeRevision;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SubmitGradeAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(StudentSubjectGrade $grade, TeachingAssignment $assignment, User $actor): void
    {
        Gate::forUser($actor)->authorize('update', [$grade, $assignment]);
        DB::transaction(function () use ($grade, $assignment, $actor): void {
            $grade = StudentSubjectGrade::query()->lockForUpdate()->findOrFail($grade->id);
            Gate::forUser($actor)->authorize('update', [$grade, $assignment]);
            if ($grade->status !== 'draft' || ! $grade->isComplete() || $grade->final_grade === null) {
                throw ValidationException::withMessages(['grade' => 'Enter all four quarter grades before submitting a draft.']);
            }
            $grade->update(['status' => 'submitted', 'submitted_by' => $actor->id, 'submitted_at' => now()]);
            GradeRevision::query()->create(['student_subject_grade_id' => $grade->id, 'event_type' => 'submitted', 'actor_user_id' => $actor->id, 'occurred_at' => now()]);
            $this->audit->record($actor, 'grading.submitted', $grade);
        });
    }
}
