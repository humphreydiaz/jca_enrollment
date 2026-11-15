<?php

namespace App\Modules\Academics\Actions;

use App\Models\User;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Archives a subject offering that a section no longer takes, or restores it. Archiving keeps the
 * offering and its grades for history, hides it from current class schedules, and deactivates its
 * subject-teacher assignments so no new grades are entered against it.
 */
class SetSectionSubjectStatusAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(SectionSubject $sectionSubject, string $status, User $actor): void
    {
        DB::transaction(function () use ($sectionSubject, $status, $actor): void {
            $offering = SectionSubject::query()->lockForUpdate()->findOrFail($sectionSubject->id);
            if ($offering->status === $status) {
                throw ValidationException::withMessages(['status' => $status === 'archived' ? 'This subject is already archived.' : 'This subject is already active.']);
            }

            $deactivated = 0;
            if ($status === 'archived') {
                $deactivated = TeachingAssignment::query()
                    ->where('section_subject_id', $offering->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
            $offering->update(['status' => $status]);
            $this->audit->record($actor, $status === 'archived' ? 'academics.section_subject.archived' : 'academics.section_subject.restored', $offering, [
                'section_id' => $offering->section_id,
                'subject_id' => $offering->subject_id,
                'teaching_assignments_deactivated' => $deactivated,
            ]);
        });
    }
}
