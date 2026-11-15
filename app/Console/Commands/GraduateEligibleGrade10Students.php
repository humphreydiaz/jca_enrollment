<?php

namespace App\Console\Commands;

use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:graduate-eligible-grade10-students {--dry-run : Preview without changing student records}')]
#[Description('Graduate and archive eligible Grade 10 students after their academic year ends.')]
class GraduateEligibleGrade10Students extends Command
{
    public function handle(): int
    {
        $eligible = 0;
        $graduated = 0;
        $skipped = 0;

        $enrollments = Enrollment::query()
            ->with(['student', 'gradeLevel', 'academicYear'])
            ->where('status', 'enrolled')
            ->whereHas('gradeLevel', fn ($query) =>
                $query->where('name', 'Grade 10')
            )
            ->whereHas('academicYear', fn ($query) =>
                $query->whereDate('ends_on', '<=', today()->toDateString())
            )
            ->get();

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;

            if (!$student || $student->archived_at !== null) {
                $skipped++;
                continue;
            }

            $assignment = SectionAssignment::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('status', 'confirmed')
                ->whereNull('ended_at')
                ->first();

            if (!$assignment) {
                $skipped++;
                continue;
            }

            $requiredSubjects = SectionSubject::query()
                ->where('section_id', $assignment->section_id)
                ->where('status', 'active')
                ->pluck('subject_id')
                ->unique();

            if ($requiredSubjects->isEmpty()) {
                $skipped++;
                continue;
            }

            $grades = StudentSubjectGrade::query()
                ->where('enrollment_id', $enrollment->id)
                ->whereIn('subject_id', $requiredSubjects)
                ->withCount([
                    'corrections as pending_corrections_count' =>
                        fn ($query) => $query->where('status', 'pending'),
                ])
                ->get()
                ->keyBy('subject_id');

            $passed = $requiredSubjects->every(function ($subjectId) use ($grades) {
                $grade = $grades->get($subjectId);

                return $grade !== null
                    && $grade->status === 'finalized'
                    && $grade->isComplete()
                    && $grade->final_grade !== null
                    && $grade->final_grade >= 75
                    && (int) $grade->pending_corrections_count === 0;
            });

            if (!$passed) {
                $skipped++;
                continue;
            }

            $eligible++;

            if ($this->option('dry-run')) {
                $this->line("Eligible: {$student->public_id}");
                continue;
            }

            $updated = DB::transaction(function () use ($student, $enrollment) {
                $lockedStudent = $student->newQuery()
                    ->whereKey($student->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedStudent->archived_at !== null) {
                    return false;
                }

                $lockedStudent->update([
                    'status' => 'graduated',
                    'graduated_at' => $enrollment->academicYear->ends_on,
                    'archived_at' => now(),
                    'archive_reason' => 'Completed Grade 10 with all required subjects passed.',
                ]);

                return true;
            });

            if ($updated) {
                $graduated++;
            }
        }

        $this->info(
            "Eligible: {$eligible}; Graduated: {$graduated}; Skipped: {$skipped}"
        );

        return self::SUCCESS;
    }
}

