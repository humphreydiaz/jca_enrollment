<?php

namespace App\Modules\StudentSupport\Actions;

use App\Models\User;
use App\Modules\Shared\Audit\AuditRecorder;
use App\Modules\StudentSupport\Models\SupportCase;
use App\Modules\StudentSupport\Models\SupportCaseEvent;
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

    public function transition(SupportCase $case, string $outcome, User $actor): SupportCase
    {
        return DB::transaction(function () use ($case, $outcome, $actor): SupportCase {
            $record = SupportCase::query()->lockForUpdate()->findOrFail($case->id);
            if (! in_array($record->status, ['assigned', 'open'], true)) {
                throw ValidationException::withMessages(['outcome' => 'This review cannot be transitioned from its current status.']);
            } $from = $record->status;
            $to = $outcome === 'pending' ? 'assigned' : 'reviewed';
            $record->update(['status' => $to, 'final_outcome' => $outcome === 'approved' ? 'approved' : null]);
            SupportCaseEvent::query()->create(['support_case_id' => $record->id, 'event_type' => 'outcome_recorded', 'from_status' => $from, 'to_status' => $to, 'actor_user_id' => $actor->id, 'occurred_at' => now()]);
            $this->audit->record($actor, 'support.outcome.recorded', $record, ['outcome' => $outcome]);

            return $record;
        });
    }
}
