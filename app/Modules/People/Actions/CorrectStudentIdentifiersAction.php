<?php

namespace App\Modules\People\Actions;

use App\Models\User;
use App\Modules\People\Models\StudentProfile;
use App\Modules\People\Models\StudentProfileCorrection;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorrectStudentIdentifiersAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /** @param array{student_number: string, lrn?: string|null} $identifiers */
    public function execute(StudentProfile $student, array $identifiers, string $reason, User $actor): StudentProfile
    {
        return DB::transaction(function () use ($actor, $identifiers, $reason, $student): StudentProfile {
            $record = StudentProfile::query()->lockForUpdate()->findOrFail($student->id);
            $changes = [];

            foreach (['student_number', 'lrn'] as $field) {
                $after = filled($identifiers[$field] ?? null) ? trim((string) $identifiers[$field]) : null;
                $before = $record->getAttribute($field);
                if ($before === $after) {
                    continue;
                }

                $changes[$field] = $after;
                StudentProfileCorrection::query()->create([
                    'student_id' => $record->id,
                    'actor_user_id' => $actor->id,
                    'field_name' => $field,
                    'before_value' => $before,
                    'after_value' => $after,
                    'reason' => $reason,
                ]);
            }

            if ($changes === []) {
                throw ValidationException::withMessages(['identifiers' => 'Change the Student ID or LRN before saving.']);
            }

            $record->forceFill($changes)->save();
            $this->audit->record($actor, 'people.student.identifiers_corrected', $record, [
                'field_count' => count($changes),
            ]);

            return $record->refresh();
        });
    }
}
