<?php

namespace App\Modules\Grading\Actions;

use App\Models\User;
use App\Modules\Grading\Models\GradeRevision;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FinalizeGradeAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(StudentSubjectGrade $grade, User $actor): void
    {
        Gate::forUser($actor)->authorize('finalize', $grade);
        DB::transaction(function () use ($grade, $actor): void {
            $grade = StudentSubjectGrade::query()->lockForUpdate()->findOrFail($grade->id);
            if ($grade->status !== 'submitted' || ! $grade->isComplete() || $grade->final_grade === null
                || $grade->corrections()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['grade' => 'Only a complete submitted result with no pending correction may be finalized.']);
            }
            $grade->update(['status' => 'finalized', 'finalized_by' => $actor->id, 'finalized_at' => now()]);
            GradeRevision::query()->create(['student_subject_grade_id' => $grade->id, 'event_type' => 'finalized', 'actor_user_id' => $actor->id, 'occurred_at' => now()]);
            $this->audit->record($actor, 'grading.finalized', $grade);
        });
    }
}
