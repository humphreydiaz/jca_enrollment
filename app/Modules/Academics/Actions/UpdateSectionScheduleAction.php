<?php

namespace App\Modules\Academics\Actions;

use App\Models\User;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Support\ClassSchedule;
use App\Modules\Academics\Support\ScheduleConflictGuard;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

class UpdateSectionScheduleAction
{
    public function __construct(private readonly ScheduleConflictGuard $guard, private readonly AuditRecorder $audit) {}

    /** @param array<string, mixed> $input days[], starts_at, ends_at, and/or a legacy schedule_text */
    public function execute(SectionSubject $sectionSubject, array $input, User $actor): void
    {
        DB::transaction(function () use ($sectionSubject, $input, $actor): void {
            $sectionSubject = SectionSubject::query()->lockForUpdate()->findOrFail($sectionSubject->id);
            $attributes = ClassSchedule::attributesFromInput($input);
            $schedule = ClassSchedule::make($attributes['days'], $attributes['starts_at'], $attributes['ends_at']);
            foreach ($sectionSubject->teachingAssignments()->where('is_active', true)->where('assignment_type', 'subject_teacher')->get() as $assignment) {
                $this->guard->assertTeacherAvailable($assignment->staff_profile_id, $sectionSubject, $schedule);
            }
            $sectionSubject->update($attributes);
            $this->audit->record($actor, 'academics.section_subject.updated', $sectionSubject);
        });
    }
}
