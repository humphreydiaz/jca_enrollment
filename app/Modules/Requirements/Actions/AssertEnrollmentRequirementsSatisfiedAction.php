<?php

namespace App\Modules\Requirements\Actions;

use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Validation\ValidationException;

class AssertEnrollmentRequirementsSatisfiedAction
{
    public function execute(Enrollment $enrollment): void
    {
        $enrollment->loadMissing('requirements');
        $required = $enrollment->requirements->filter(fn ($requirement): bool => (bool) ($requirement->rule_snapshot['is_required'] ?? false));
        if ($required->isEmpty()) {
            throw ValidationException::withMessages(['status' => 'The applicable requirement checklist has not been configured.']);
        }

        $unresolved = $enrollment->requirements
            ->filter(fn ($requirement): bool => (bool) ($requirement->rule_snapshot['is_blocking'] ?? false))
            ->reject(function ($requirement): bool {
                $hardCopyAccepted = $requirement->status === 'verified'
                    || ($requirement->status === 'submitted' && $requirement->received_at !== null);

                return $hardCopyAccepted
                    || $requirement->soft_copy_status === 'verified'
                    || $requirement->status === 'waived';
            });

        if ($unresolved->isNotEmpty()) {
            throw ValidationException::withMessages([
                'status' => 'Each blocking requirement needs either a verified soft copy or a recorded hard-copy receipt before approval.',
            ]);
        }
    }
}
