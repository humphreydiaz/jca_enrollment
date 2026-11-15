<?php

namespace App\Modules\Academics\Support;

use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Models\TeachingAssignment;
use Illuminate\Validation\ValidationException;

class ScheduleConflictGuard
{
    public function overlaps(?ClassSchedule $left, ?ClassSchedule $right): bool
    {
        return $left !== null && $right !== null && $left->overlaps($right);
    }

    public function assertTeacherAvailable(int $staffProfileId, SectionSubject $candidate, ?ClassSchedule $schedule = null): void
    {
        $schedule ??= ClassSchedule::fromRecord($candidate);
        if ($schedule === null) {
            return;
        }
        $candidate->loadMissing('section');
        $assignments = TeachingAssignment::query()->with(['section', 'sectionSubject.subject'])
            ->where('staff_profile_id', $staffProfileId)
            ->where('assignment_type', 'subject_teacher')
            ->where('is_active', true)
            ->whereHas('section', fn ($query) => $query->where('academic_year_id', $candidate->section->academic_year_id))
            ->whereHas('sectionSubject', fn ($query) => $query->where('status', 'active'))
            ->where('section_subject_id', '!=', $candidate->id)->get();
        foreach ($assignments as $assignment) {
            if ($this->overlaps($schedule, $assignment->sectionSubject ? ClassSchedule::fromRecord($assignment->sectionSubject) : null)) {
                throw ValidationException::withMessages(['schedule_text' => sprintf(
                    'This teacher already teaches %s in %s at that time. Edit the schedule before assigning the teacher.',
                    $assignment->sectionSubject->subject?->name ?? 'another subject',
                    $assignment->section->name,
                )]);
            }
        }
    }
}
