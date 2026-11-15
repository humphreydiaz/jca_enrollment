<?php

namespace App\Modules\People\Actions;

use App\Models\User;
use App\Modules\People\Models\StudentProfile;
use App\Modules\People\Models\StudentProfileCorrection;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateStudentProfileAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /** @param array<string, mixed> $attributes */
    public function execute(StudentProfile $student, array $attributes, string $reason, User $actor): StudentProfile
    {
        return DB::transaction(function () use ($actor, $attributes, $reason, $student): StudentProfile {
            $record = StudentProfile::query()->lockForUpdate()->findOrFail($student->id);
            $changes = [];

            foreach ($attributes as $field => $value) {
                $value = is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value;
                if (in_array($field, ['four_ps_household_id'], true) && ! $attributes['is_4ps_recipient']) {
                    $value = null;
                }
                if (in_array($field, ['disability_type'], true) && ! $attributes['is_learner_with_disability']) {
                    $value = null;
                }

                $before = $record->getAttribute($field);
                $beforeComparable = $before instanceof \DateTimeInterface ? $before->format('Y-m-d') : $before;
                if ($this->historyValue($beforeComparable) === $this->historyValue($value)) {
                    continue;
                }

                $changes[$field] = $value;
                StudentProfileCorrection::query()->create([
                    'student_id' => $record->id,
                    'actor_user_id' => $actor->id,
                    'field_name' => $field,
                    'before_value' => $this->historyValue($beforeComparable),
                    'after_value' => $this->historyValue($value),
                    'reason' => $reason,
                ]);
            }

            if ($changes === []) {
                throw ValidationException::withMessages(['profile' => 'Change at least one learner-information field before saving.']);
            }

            $record->forceFill($changes)->save();
            $this->audit->record($actor, 'people.student.profile_corrected', $record, [
                'field_count' => count($changes),
                // Names only: the before/after values live in student_profile_corrections.
                'fields' => array_keys($changes),
            ]);

            return $record->refresh();
        });
    }

    private function historyValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
