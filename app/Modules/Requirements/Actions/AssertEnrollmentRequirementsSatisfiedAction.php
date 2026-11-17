<?php

namespace App\Modules\Requirements\Actions;

use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Validation\ValidationException;

class AssertEnrollmentRequirementsSatisfiedAction
{
    public function execute(Enrollment $enrollment): void
    {
        $enrollment->loadMissing('requirements');

        $required = $enrollment->requirements->filter(
            fn ($requirement): bool =>
                (bool) ($requirement->rule_snapshot['is_required'] ?? false)
        );

        if ($required->isEmpty()) {
            throw ValidationException::withMessages([
                'status' => 'The applicable hard-copy requirement checklist has not been configured.',
            ]);
        }

        $unresolved = $enrollment->requirements
            ->filter(
                fn ($requirement): bool =>
                    (bool) ($requirement->rule_snapshot['is_blocking'] ?? false)
            )
            ->reject(function ($requirement): bool {
                $hardCopyReceived =
                    $requirement->received_at !== null
                    && in_array(
                        $requirement->status,
                        ['submitted', 'verified'],
                        true
                    );

                $officiallyWaived =
                    $requirement->status === 'waived'
                    && $requirement->waived_by !== null;

                return $hardCopyReceived || $officiallyWaived;
            });

        if ($unresolved->isNotEmpty()) {
            throw ValidationException::withMessages([
                'status' =>
                    'All blocking requirements must have a recorded hard-copy receipt or an authorized waiver before approval.',
            ]);
        }
    }
}