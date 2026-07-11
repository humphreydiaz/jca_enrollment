<?php

namespace App\Modules\Enrollment\Actions;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Models\EnrollmentStatusHistory;
use App\Modules\Requirements\Actions\GenerateEnrollmentChecklistAction;
use App\Modules\Shared\Audit\AuditRecorder;
use App\Modules\StudentSupport\Models\SupportCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionEnrollmentAction
{
    public function __construct(private AuditRecorder $audit, private GenerateEnrollmentChecklistAction $checklist) {}

    public function execute(Enrollment $enrollment, string $to, ?User $actor, string $reason = ''): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $to, $actor, $reason) {
            $record = Enrollment::query()->lockForUpdate()->with('requirements')->findOrFail($enrollment->id);
            $from = $record->status;
            $allowed = [
                'draft' => ['submitted', 'cancelled'],
                'submitted' => ['under_review', 'incomplete', 'assessment_required', 'waitlisted', 'rejected', 'cancelled'],
                'under_review' => ['incomplete', 'assessment_required', 'approved', 'waitlisted', 'rejected', 'cancelled'],
                'incomplete' => ['submitted', 'rejected', 'cancelled'],
                'assessment_required' => ['assessment_pending', 'rejected', 'cancelled'],
                'assessment_pending' => ['under_review', 'rejected', 'cancelled'],
                'waitlisted' => ['under_review', 'rejected', 'cancelled'],
                'approved' => ['withdrawn'],
            ];
            if (! in_array($to, $allowed[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'This enrollment transition is not allowed.']);
            }
            if ($to === 'submitted' && $record->contact_verified_at === null) {
                throw ValidationException::withMessages(['status' => 'Contact verification is required before submission.']);
            }
            if ($to === 'under_review' && $record->duplicate_review_status === 'pending') {
                throw ValidationException::withMessages(['status' => 'Potential duplicate identity must be reviewed first.']);
            }
            if ($to === 'submitted') {
                $this->checklist->execute($record);
                $record->submitted_at = now();
            }
            if ($to === 'approved') {
                $record->load('requirements');
                if ($record->duplicate_review_status === 'pending') {
                    throw ValidationException::withMessages(['status' => 'Potential duplicate identity must be reviewed first.']);
                }
                if ($record->requirements->filter(fn ($requirement) => (bool) ($requirement->rule_snapshot['is_required'] ?? false))->isEmpty()) {
                    throw ValidationException::withMessages(['status' => 'The applicable requirement checklist has not been configured.']);
                }
                if ($record->requirements->whereIn('status', ['missing', 'submitted', 'under_review', 'rejected'])->where(fn ($r) => ($r->rule_snapshot['is_blocking'] ?? false))->isNotEmpty()) {
                    throw ValidationException::withMessages(['status' => 'Blocking requirements must be resolved.']);
                }
                if ($record->student_category === 'support_review' && ! SupportCase::query()->where('enrollment_id', $record->id)->where('final_outcome', 'approved')->exists()) {
                    throw ValidationException::withMessages(['status' => 'An approved support review is required.']);
                }
                $record->approved_at = now();
            }
            $record->status = $to;
            if (in_array($to, ['rejected', 'withdrawn', 'cancelled'], true)) {
                if ($to === 'rejected') {
                    $record->rejected_at = now();
                }
                $record->active_enrollment_key = null;
            }
            $record->decided_by = $actor?->id;
            $record->decision_reason = $reason ?: null;
            $record->lock_version++;
            $record->save();
            EnrollmentStatusHistory::query()->create(['enrollment_id' => $record->id, 'from_status' => $from, 'to_status' => $to, 'reason' => $reason ?: null, 'changed_by' => $actor?->id, 'changed_at' => now()]);
            if (in_array($to, ['approved', 'rejected', 'waitlisted'], true)) {
                DB::table('enrollment_decisions')->insert([
                    'enrollment_id' => $record->id,
                    'decision_type' => 'academic_admission',
                    'outcome' => $to,
                    'reason' => $reason ?: null,
                    'decided_by' => $actor?->id,
                    'decided_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->audit->record($actor, 'enrollment.status.changed', $record, ['from' => $from, 'to' => $to]);

            return $record;
        });
    }
}
