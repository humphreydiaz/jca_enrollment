<?php

namespace App\Modules\SectionAssignment\Actions;

use App\Models\User;
use App\Modules\Academics\Models\Section;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use App\Modules\SectionAssignment\Models\SectionAssignmentEvent;
use App\Modules\Shared\Audit\AuditRecorder;
use App\Modules\StudentSupport\Models\SupportCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferSectionAssignmentAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(SectionAssignment $assignment, Section $destination, User $actor, string $reason, bool $supportSuitability, bool $capacityOverride = false, ?string $overrideReason = null): SectionAssignment
    {
        return DB::transaction(function () use ($assignment, $destination, $actor, $reason, $supportSuitability, $capacityOverride, $overrideReason): SectionAssignment {
            $current = SectionAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $enrollment = Enrollment::query()->lockForUpdate()->findOrFail($current->enrollment_id);
            $sections = Section::query()->whereIn('id', [$current->section_id, $destination->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $target = $sections->get($destination->id);

            if ($current->status !== 'confirmed' || $current->ended_at !== null || $current->active_assignment_key === null || $enrollment->status !== 'enrolled') {
                throw ValidationException::withMessages(['assignment' => 'Only the current confirmed assignment of an enrolled student can be transferred.']);
            }
            if ($current->section_id === $target?->id) {
                throw ValidationException::withMessages(['section' => 'Select a different destination section.']);
            }
            if ($target === null || $target->status !== 'active' || $target->academic_year_id !== $enrollment->academic_year_id || $target->grade_level_id !== $enrollment->grade_level_id) {
                throw ValidationException::withMessages(['section' => 'The destination must be active and match the enrollment school year and grade.']);
            }
            if ($enrollment->student_category === 'support_review') {
                $approved = SupportCase::query()->where('enrollment_id', $enrollment->id)->where('final_outcome', 'approved')->exists();
                if (! $approved || ! $supportSuitability) {
                    throw ValidationException::withMessages(['support_suitability_confirmed' => 'Explicit placement suitability confirmation is required for this destination.']);
                }
            }

            $confirmedCount = SectionAssignment::query()->where('section_id', $target->id)->where('status', 'confirmed')->whereNull('ended_at')->count();
            if ($confirmedCount >= $target->capacity && ! $capacityOverride) {
                throw ValidationException::withMessages(['section' => 'The destination section is already at capacity.']);
            }
            if ($capacityOverride && $confirmedCount < $target->capacity) {
                throw ValidationException::withMessages(['override_capacity' => 'A capacity override is not needed while space remains.']);
            }
            if ($capacityOverride && ! $actor->hasRole('admin')) {
                throw new AuthorizationException('Only an Admin may override section capacity.');
            }
            if ($capacityOverride && blank($overrideReason)) {
                throw ValidationException::withMessages(['override_reason' => 'A capacity override reason is required.']);
            }

            $current->update(['status' => 'ended', 'active_assignment_key' => null, 'ended_at' => now(), 'ended_by' => $actor->id, 'ended_reason' => $reason]);
            $newAssignment = SectionAssignment::query()->create([
                'enrollment_id' => $enrollment->id,
                'section_id' => $target->id,
                'status' => 'confirmed',
                'active_assignment_key' => (string) $enrollment->id,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
                'effective_from' => today(),
                'transferred_from_id' => $current->id,
                'support_suitability_confirmed' => $enrollment->student_category === 'support_review' && $supportSuitability,
                'suitability_confirmed_by' => $enrollment->student_category === 'support_review' ? $actor->id : null,
                'suitability_confirmed_at' => $enrollment->student_category === 'support_review' ? now() : null,
                'is_capacity_override' => $capacityOverride,
                'capacity_override_reason' => $capacityOverride ? $overrideReason : null,
                'capacity_override_by' => $capacityOverride ? $actor->id : null,
            ]);
            SectionAssignmentEvent::query()->create([
                'section_assignment_id' => $newAssignment->id,
                'enrollment_id' => $enrollment->id,
                'event_type' => $capacityOverride ? 'transferred_override' : 'transferred',
                'from_section_id' => $current->section_id,
                'to_section_id' => $target->id,
                'actor_user_id' => $actor->id,
                'reason' => $reason.($capacityOverride ? ' Capacity override: '.$overrideReason : ''),
                'is_capacity_override' => $capacityOverride,
                'occurred_at' => now(),
            ]);
            $this->audit->record($actor, 'section_assignment.transferred', $newAssignment, ['capacity_override' => $capacityOverride]);

            return $newAssignment;
        });
    }
}
