<?php

namespace App\Modules\Requirements\Actions;

use App\Models\User;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewRequirementAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(EnrollmentRequirement $requirement, string $status, User $actor, ?string $reason): EnrollmentRequirement
    {
        return DB::transaction(function () use ($requirement, $status, $actor, $reason): EnrollmentRequirement {
            $record = EnrollmentRequirement::query()->lockForUpdate()->findOrFail($requirement->id);
            if (in_array($status, ['rejected', 'waived'], true) && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'A reason is required for rejection or waiver.']);
            }
            if (in_array($status, ['verified', 'rejected'], true) && $record->received_at === null) {
                throw ValidationException::withMessages(['status' => 'Record receipt of the hard copy before reviewing this requirement.']);
            }
            if (in_array($status, ['verified', 'rejected'], true) && ! in_array($record->status, ['submitted', 'verified'], true)) {
                throw ValidationException::withMessages(['status' => 'Record a newly received hard copy before reviewing this requirement again.']);
            }
            if ($status === 'waived' && ! in_array($record->status, ['missing', 'submitted', 'rejected', 'verified'], true)) {
                throw ValidationException::withMessages(['status' => 'This requirement cannot be reviewed from its current state.']);
            }
            $record->update(['status' => $status, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_reason' => $reason, 'waived_by' => $status === 'waived' ? $actor->id : null]);
            $this->audit->record($actor, 'requirements.requirement.'.$status, $record);

            return $record;
        });
    }
}
