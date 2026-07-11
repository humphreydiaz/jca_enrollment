<?php

namespace App\Modules\Requirements\Actions;

use App\Models\User;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\Shared\Audit\AuditRecorder;

class ReviewRequirementAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(EnrollmentRequirement $requirement, string $status, User $actor, ?string $reason): EnrollmentRequirement
    {
        $requirement->update(['status' => $status, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_reason' => $reason, 'waived_by' => $status === 'waived' ? $actor->id : null]);
        $this->audit->record($actor, 'requirements.requirement.'.$status, $requirement);

        return $requirement;
    }
}
