<?php

namespace App\Modules\Requirements\Actions;

use App\Models\User;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordHardCopyReceiptAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(EnrollmentRequirement $requirement, bool $received, User $actor, ?string $notes): EnrollmentRequirement
    {
        return DB::transaction(function () use ($requirement, $received, $actor, $notes): EnrollmentRequirement {
            $record = EnrollmentRequirement::query()->lockForUpdate()->findOrFail($requirement->id);

            if ($received && ! in_array($record->status, ['missing', 'submitted', 'rejected'], true)) {
                throw ValidationException::withMessages(['received' => 'This requirement can no longer be marked as newly received.']);
            }

            if (! $received && ! in_array($record->status, ['missing', 'submitted'], true)) {
                throw ValidationException::withMessages(['received' => 'A reviewed requirement cannot be marked as not received.']);
            }

            $record->update($received ? [
                'status' => 'submitted',
                'received_by' => $actor->id,
                'received_at' => now(),
                'receipt_notes' => $notes,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_reason' => null,
                'waived_by' => null,
            ] : [
                'status' => 'missing',
                'received_by' => null,
                'received_at' => null,
                'receipt_notes' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_reason' => null,
                'waived_by' => null,
            ]);

            $this->audit->record(
                $actor,
                $received ? 'requirements.hard_copy.received' : 'requirements.hard_copy.marked_missing',
                $record,
            );

            return $record;
        });
    }
}
