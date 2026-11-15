<?php

namespace App\Modules\Grading\Actions;

use App\Models\User;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Grading\Models\GradeRevision;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveQuarterGradesAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /** @param array<string, int|string|null> $quarters */
    public function execute(TeachingAssignment $assignment, Enrollment $enrollment, User $actor, array $quarters): StudentSubjectGrade
    {
        Gate::forUser($actor)->authorize('create', [StudentSubjectGrade::class, $assignment, $enrollment]);

        return DB::transaction(function () use ($assignment, $enrollment, $actor, $quarters): StudentSubjectGrade {
            // Serialize first creation for one enrollment, even when two teachers save concurrently.
            Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('create', [StudentSubjectGrade::class, $assignment, $enrollment->fresh()]);
            $offering = $assignment->sectionSubject()->with('subject')->firstOrFail();
            $grade = StudentSubjectGrade::query()->where('enrollment_id', $enrollment->id)
                ->where('subject_id', $offering->subject_id)->lockForUpdate()->first();

            if ($grade === null) {
                $grade = StudentSubjectGrade::query()->create([
                    'enrollment_id' => $enrollment->id,
                    'subject_id' => $offering->subject_id,
                    'section_subject_id' => $offering->id,
                    'teaching_assignment_id' => $assignment->id,
                    'subject_code_snapshot' => $offering->subject->code,
                    'subject_name_snapshot' => $offering->subject->name,
                    'status' => 'draft',
                ]);
            }

            Gate::forUser($actor)->authorize('update', [$grade, $assignment]);
            if ($grade->status !== 'draft') {
                throw ValidationException::withMessages(['grade' => 'This grade is locked. Ask the Registrar to reopen it before editing.']);
            }

            $changed = false;
            foreach ([1, 2, 3, 4] as $quarter) {
                $column = "quarter_{$quarter}";
                $new = isset($quarters[$column]) && $quarters[$column] !== '' ? (int) $quarters[$column] : null;
                $old = $grade->{$column};
                if ($old === $new) {
                    continue;
                }
                $grade->{$column} = $new;
                GradeRevision::query()->create([
                    'student_subject_grade_id' => $grade->id, 'event_type' => 'quarter_saved',
                    'quarter_number' => $quarter, 'old_value' => $old, 'new_value' => $new,
                    'actor_user_id' => $actor->id, 'occurred_at' => now(),
                ]);
                $changed = true;
            }

            // Whole-number quarter grades; round an exact .5 average upward.
            $grade->final_grade = $grade->isComplete()
                ? intdiv($grade->quarter_1 + $grade->quarter_2 + $grade->quarter_3 + $grade->quarter_4 + 2, 4)
                : null;
            $grade->save();

            foreach ([1, 2, 3, 4] as $quarter) {
                DB::table('grading_periods')->insertOrIgnore([
                    'academic_year_id' => $enrollment->academic_year_id,
                    'quarter_number' => $quarter,
                    'name' => "Quarter {$quarter}",
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            if ($changed) {
                $this->audit->record($actor, 'grading.quarters.saved', $grade, ['complete' => $grade->isComplete()]);
            }

            return $grade;
        });
    }
}
