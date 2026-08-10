<?php

namespace App\Modules\Requirements\Actions;

use App\Models\User;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewSoftCopyAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(EnrollmentRequirement $requirement, string $status, User $actor, ?string $reason): EnrollmentRequirement
    {
        return DB::transaction(function () use ($requirement, $status, $actor, $reason): EnrollmentRequirement {
            $record = EnrollmentRequirement::query()->lockForUpdate()->with('activeUpload')->findOrFail($requirement->id);
            if (! in_array($status, ['verified', 'rejected'], true)) {
                throw ValidationException::withMessages(['soft_copy_status' => 'Select a valid soft-copy review decision.']);
            }
            if ($record->activeUpload === null || ! in_array($record->soft_copy_status, ['submitted', 'verified'], true)) {
                throw ValidationException::withMessages(['soft_copy_status' => 'A current submitted soft copy is required before review.']);
            }
            if ($status === 'rejected' && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'Explain why a replacement soft copy is required.']);
            }

            $record->update([
                'soft_copy_status' => $status,
                'soft_copy_reviewed_by' => $actor->id,
                'soft_copy_reviewed_at' => now(),
                'soft_copy_review_reason' => $reason,
            ]);
            $this->audit->record($actor, 'requirements.soft_copy.'.$status, $record);

            return $record;
        });
    }
}
