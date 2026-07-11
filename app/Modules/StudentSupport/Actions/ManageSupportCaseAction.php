<?php

namespace App\Modules\StudentSupport\Actions;

use App\Models\User;
use App\Modules\Shared\Audit\AuditRecorder;
use App\Modules\StudentSupport\Models\ApprovedSupportSummary;
use App\Modules\StudentSupport\Models\SupportAssessment;
use App\Modules\StudentSupport\Models\SupportCase;
use App\Modules\StudentSupport\Models\SupportCaseEvent;
use App\Modules\StudentSupport\Models\SupportObservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageSupportCaseAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function assign(SupportCase $case, User $reviewer, User $actor): SupportCase
    {
        return DB::transaction(function () use ($case, $reviewer, $actor): SupportCase {
            $record = SupportCase::query()->lockForUpdate()->findOrFail($case->id);
            $from = $record->status;
            $record->update(['assigned_reviewer_id' => $reviewer->id, 'status' => 'assigned']);
            SupportCaseEvent::query()->create(['support_case_id' => $record->id, 'event_type' => 'reviewer_assigned', 'from_status' => $from, 'to_status' => 'assigned', 'actor_user_id' => $actor->id, 'reviewer_user_id' => $reviewer->id, 'occurred_at' => now()]);
            $this->audit->record($actor, 'support.reviewer.assigned', $record);

            return $record;
        });
    }

    /** @param array<string, mixed> $data */
    public function recordAssessment(SupportCase $case, array $data, User $actor): SupportCase
    {
        return DB::transaction(function () use ($case, $data, $actor): SupportCase {
            $record = SupportCase::query()->lockForUpdate()->findOrFail($case->id);
            if ($record->assigned_reviewer_id !== null && $record->assigned_reviewer_id !== $actor->id && ! $actor->hasRole('admin')) {
                throw ValidationException::withMessages(['outcome' => 'Only the assigned reviewer may record this outcome.']);
            }
            SupportAssessment::query()->create(array_merge($data, [
                'support_case_id' => $record->id,
                'assessment_type' => 'school_review',
                'completed_on' => now(),
                'reviewer_id' => $actor->id,
            ]));

            return $this->transition($record, $data['outcome'], $actor);
        });
    }

    /** @param array<string, mixed> $data */
    public function addObservation(SupportCase $case, array $data, User $actor): void
    {
        DB::transaction(function () use ($case, $data, $actor): void {
            $record = SupportCase::query()->lockForUpdate()->findOrFail($case->id);
            SupportObservation::query()->create(array_merge($data, ['support_case_id' => $record->id, 'author_id' => $actor->id, 'observed_at' => now()]));
            SupportCaseEvent::query()->create(['support_case_id' => $record->id, 'event_type' => 'observation_added', 'actor_user_id' => $actor->id, 'occurred_at' => now()]);
            $this->audit->record($actor, 'support.observation.added', $record);
        });
    }

    /** @param array<string, mixed> $data */
    public function approveSummary(SupportCase $case, array $data, User $actor): void
    {
        DB::transaction(function () use ($case, $data, $actor): void {
            $record = SupportCase::query()->lockForUpdate()->findOrFail($case->id);
            if ($record->final_outcome !== 'approved') {
                throw ValidationException::withMessages(['summary' => 'A teacher-safe summary can be approved only after an approved review outcome.']);
            }
            $previous = ApprovedSupportSummary::query()->where('support_case_id', $record->id)->whereNull('superseded_by')->whereNotNull('approved_at')->get();
            $new = ApprovedSupportSummary::query()->create(array_merge($data, ['support_case_id' => $record->id, 'approved_by' => $actor->id, 'approved_at' => now()]));
            ApprovedSupportSummary::query()->whereKey($previous->modelKeys())->update(['superseded_by' => $new->id]);
            SupportCaseEvent::query()->create(['support_case_id' => $record->id, 'event_type' => 'summary_approved', 'actor_user_id' => $actor->id, 'occurred_at' => now()]);
            $this->audit->record($actor, 'support.summary.approved', $record);
        });
    }

    public function transition(SupportCase $case, string $outcome, User $actor): SupportCase
    {
        return DB::transaction(function () use ($case, $outcome, $actor): SupportCase {
            $record = SupportCase::query()->lockForUpdate()->findOrFail($case->id);
            if (! in_array($record->status, ['assigned', 'open'], true)) {
                throw ValidationException::withMessages(['outcome' => 'This review cannot be transitioned from its current status.']);
            }
            $from = $record->status;
            $to = in_array($outcome, ['pending', 'more_information_required'], true) ? 'assigned' : 'reviewed';
            $record->update(['status' => $to, 'final_outcome' => in_array($outcome, ['approved', 'not_approved'], true) ? $outcome : null]);
            SupportCaseEvent::query()->create(['support_case_id' => $record->id, 'event_type' => 'outcome_recorded', 'from_status' => $from, 'to_status' => $to, 'actor_user_id' => $actor->id, 'occurred_at' => now()]);
            $this->audit->record($actor, 'support.outcome.recorded', $record, ['outcome' => $outcome]);

            return $record;
        });
    }
}
