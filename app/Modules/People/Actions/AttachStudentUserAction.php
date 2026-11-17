<?php

namespace App\Modules\People\Actions;

use App\Models\User;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

/**
 * Connects a Student login that the Registrar issued in person (walk-in access or a granted
 * elementary account) to the learner record it was created for. Student pages (grades,
 * finance, schedule) find "my learner" through this connection (D-048).
 */
class AttachStudentUserAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(StudentProfile $student, User $account, User $actor, string $context): StudentProfile
    {
        $student = StudentProfile::query()->lockForUpdate()->findOrFail($student->id);
        if ($student->user_id === $account->id) {
            return $student;
        }
        if ($student->user_id !== null) {
            throw ValidationException::withMessages(['student' => 'This learner already has a Student account.']);
        }
        if (StudentProfile::query()->where('user_id', $account->id)->exists()) {
            throw ValidationException::withMessages(['student' => 'This Student account already belongs to another learner.']);
        }

        $student->forceFill(['user_id' => $account->id])->save();
        $this->audit->record($actor, 'people.student.account_attached', $student, ['context' => $context, 'linked_user_public_id' => $account->public_id]);

        return $student;
    }
}
