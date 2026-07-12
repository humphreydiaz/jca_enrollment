<?php

namespace App\Modules\SectionAssignment\Actions;

use App\Models\User;
use App\Modules\Academics\Models\Section;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Models\EnrollmentStatusHistory;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use App\Modules\SectionAssignment\Models\SectionAssignmentEvent;
use App\Modules\Shared\Audit\AuditRecorder;
use App\Modules\StudentSupport\Models\SupportCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmSectionAssignmentAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Enrollment $enrollment, Section $section, User $actor, bool $supportSuitability, bool $capacityOverride = false, ?string $overrideReason = null): SectionAssignment
    {
        return DB::transaction(function () use ($enrollment, $section, $actor, $supportSuitability, $capacityOverride, $overrideReason): SectionAssignment {
            $record = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            $destination = Section::query()->lockForUpdate()->findOrFail($section->id);
            $this->validatePlacement($record, $destination, $supportSuitability);

            if (SectionAssignment::query()->where('enrollment_id', $record->id)->where('status', 'confirmed')->whereNull('ended_at')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['section' => 'This enrollment already has a current section assignment.']);
            }

            $confirmedCount = SectionAssignment::query()->where('section_id', $destination->id)->where('status', 'confirmed')->whereNull('ended_at')->count();
            $isFull = $confirmedCount >= $destination->capacity;
            if ($isFull && ! $capacityOverride) {
                throw ValidationException::withMessages(['section' => 'The selected section is already at capacity.']);
            }
            if ($capacityOverride && ! $isFull) {
                throw ValidationException::withMessages(['override_capacity' => 'A capacity override is not needed while space remains.']);
            }
            if ($capacityOverride && ! $actor->hasRole('admin')) {
                throw new AuthorizationException('Only an Admin may override section capacity.');
            }
            if ($capacityOverride && blank($overrideReason)) {
                throw ValidationException::withMessages(['override_reason' => 'A capacity override reason is required.']);
            }

            $assignment = SectionAssignment::query()->create([
                'enrollment_id' => $record->id,
                'section_id' => $destination->id,
                'status' => 'confirmed',
                'active_assignment_key' => (string) $record->id,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'effective_from' => today(),
                'support_suitability_confirmed' => $record->student_category === 'support_review' && $supportSuitability,
                'suitability_confirmed_by' => $record->student_category === 'support_review' ? $actor->id : null,
                'suitability_confirmed_at' => $record->student_category === 'support_review' ? now() : null,
                'is_capacity_override' => $capacityOverride,
                'capacity_override_reason' => $capacityOverride ? $overrideReason : null,
                'capacity_override_by' => $capacityOverride ? $actor->id : null,
            ]);

            SectionAssignmentEvent::query()->create([
                'section_assignment_id' => $assignment->id,
                'enrollment_id' => $record->id,
                'event_type' => $capacityOverride ? 'confirmed_override' : 'confirmed',
                'to_section_id' => $destination->id,
                'actor_user_id' => $actor->id,
                'reason' => $capacityOverride ? $overrideReason : null,
                'is_capacity_override' => $capacityOverride,
                'occurred_at' => now(),
            ]);

            $record->update(['status' => 'enrolled', 'enrolled_at' => now(), 'decided_by' => $actor->id, 'decision_reason' => 'Section assignment confirmed.', 'lock_version' => $record->lock_version + 1]);
            EnrollmentStatusHistory::query()->create(['enrollment_id' => $record->id, 'from_status' => 'approved', 'to_status' => 'enrolled', 'reason' => 'Section assignment confirmed.', 'changed_by' => $actor->id, 'changed_at' => now()]);
            $this->audit->record($actor, 'section_assignment.confirmed', $assignment, ['capacity_override' => $capacityOverride]);
            $this->audit->record($actor, 'enrollment.status.changed', $record, ['from' => 'approved', 'to' => 'enrolled']);

            return $assignment;
        });
    }

    private function validatePlacement(Enrollment $enrollment, Section $section, bool $supportSuitability): void
    {
        if ($enrollment->status !== 'approved') {
            throw ValidationException::withMessages(['enrollment' => 'Only an approved enrollment can be assigned.']);
        }
        if ($section->status !== 'active' || $section->academic_year_id !== $enrollment->academic_year_id || $section->grade_level_id !== $enrollment->grade_level_id) {
            throw ValidationException::withMessages(['section' => 'The section must be active and match the enrollment school year and grade.']);
        }
        if ($enrollment->student_category === 'support_review') {
            $approved = SupportCase::query()->where('enrollment_id', $enrollment->id)->where('final_outcome', 'approved')->exists();
            if (! $approved || ! $supportSuitability) {
                throw ValidationException::withMessages(['support_suitability_confirmed' => 'An approved support outcome and explicit placement suitability confirmation are required.']);
            }
        }
    }
}
