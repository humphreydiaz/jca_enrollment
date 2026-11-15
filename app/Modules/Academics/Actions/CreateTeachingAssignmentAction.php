<?php

namespace App\Modules\Academics\Actions;

use App\Models\User;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Academics\Support\ScheduleConflictGuard;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateTeachingAssignmentAction
{
    public function __construct(
        private readonly SaveAcademicCatalogAction $save,
        private readonly ScheduleConflictGuard $scheduleGuard,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * Assign a teacher. With replace_existing, the teacher becomes the section's only active adviser
     * (or the subject's only active teacher); earlier assignments are deactivated, never deleted.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes, User $actor): TeachingAssignment
    {
        $replace = (bool) ($attributes['replace_existing'] ?? false);
        unset($attributes['replace_existing']);

        return DB::transaction(function () use ($attributes, $actor, $replace): TeachingAssignment {
            if ($attributes['assignment_type'] === 'adviser') {
                $attributes['section_subject_id'] = null;
            } else {
                $sectionSubject = SectionSubject::query()->lockForUpdate()->findOrFail($attributes['section_subject_id']);
                if ($sectionSubject->isArchived()) {
                    throw ValidationException::withMessages(['section_subject_id' => 'Restore this archived subject before assigning a teacher.']);
                }
                $this->scheduleGuard->assertTeacherAvailable($attributes['staff_profile_id'], $sectionSubject);
            }

            $sameSlot = fn () => TeachingAssignment::query()
                ->where('section_id', $attributes['section_id'])
                ->where('assignment_type', $attributes['assignment_type'])
                ->when($attributes['assignment_type'] === 'subject_teacher', fn ($query) => $query->where('section_subject_id', $attributes['section_subject_id']));

            $existing = $sameSlot()->where('staff_profile_id', $attributes['staff_profile_id'])->lockForUpdate()->first();
            if ($existing !== null && ($existing->is_active || ! $replace)) {
                throw ValidationException::withMessages(['staff_profile_id' => $existing->is_active
                    ? 'That teaching assignment already exists.'
                    : 'This teacher had this assignment before. Choose "Make this the only teacher" to reactivate it.']);
            }

            if ($replace) {
                $previous = $sameSlot()->where('is_active', true)->lockForUpdate()->get();
                foreach ($previous as $old) {
                    $old->update(['is_active' => false]);
                    $this->audit->record($actor, 'academics.teaching_assignment.replaced', $old);
                }
            }

            if ($existing !== null) {
                /** @var TeachingAssignment $assignment */
                $assignment = $this->save->execute($existing, [
                    'is_active' => true,
                    'active_from' => $attributes['active_from'] ?? $existing->active_from,
                    'active_until' => $attributes['active_until'] ?? null,
                ], $actor, 'academics.teaching_assignment.reactivated');

                return $assignment;
            }

            /** @var TeachingAssignment $assignment */
            $assignment = $this->save->execute(new TeachingAssignment, $attributes, $actor, 'academics.teaching_assignment.created');

            return $assignment;
        });
    }
}
