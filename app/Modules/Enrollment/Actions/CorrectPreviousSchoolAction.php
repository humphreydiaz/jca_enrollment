<?php

namespace App\Modules\Enrollment\Actions;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Models\EnrollmentCorrection;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorrectPreviousSchoolAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /** @param array{previous_school_name?: string|null, previous_school_address?: string|null} $attributes */
    public function execute(Enrollment $enrollment, array $attributes, string $reason, User $actor): Enrollment
    {
        return DB::transaction(function () use ($actor, $attributes, $enrollment, $reason): Enrollment {
            $record = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            $changes = [];

            foreach (['previous_school_name', 'previous_school_address'] as $field) {
                $after = filled($attributes[$field] ?? null) ? trim((string) $attributes[$field]) : null;
                $before = $record->getAttribute($field);
                if ($before === $after) {
                    continue;
                }

                $changes[$field] = $after;
                EnrollmentCorrection::query()->create([
                    'enrollment_id' => $record->id,
                    'actor_user_id' => $actor->id,
                    'field_name' => $field,
                    'before_value' => $before,
                    'after_value' => $after,
                    'reason' => $reason,
                ]);
            }

            if ($changes === []) {
                throw ValidationException::withMessages(['previous_school' => 'Change the previous-school name or address before saving.']);
            }

            $record->forceFill($changes)->save();
            $this->audit->record($actor, 'enrollment.previous_school_corrected', $record, [
                'field_count' => count($changes),
            ]);

            return $record->refresh();
        });
    }
}
