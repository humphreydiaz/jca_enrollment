<?php

namespace App\Modules\People\Actions;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\CreatedTemporaryAccess;
use App\Modules\Identity\Actions\CreateTemporaryAccessUserAction;
use App\Modules\People\Models\StudentProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gives a Nursery–Grade 6 learner their own Student login at a parent's request (D-048).
 * The parent/guardian keeps their account; both sign in with the same Student ID and are
 * told apart by their own passwords. Grades 7–10 already get a Student login at walk-in.
 */
class GrantStudentAccountAction
{
    public function __construct(
        private readonly CreateTemporaryAccessUserAction $createTemporaryAccess,
        private readonly AttachStudentUserAction $attachStudentUser,
    ) {}

    public function execute(StudentProfile $student, string $reason, User $actor): CreatedTemporaryAccess
    {
        return DB::transaction(function () use ($student, $reason, $actor): CreatedTemporaryAccess {
            $student = StudentProfile::query()->lockForUpdate()->findOrFail($student->id);
            if ($student->user_id !== null) {
                throw ValidationException::withMessages(['student' => 'This learner already has a Student account. Use “Replace password” on their row if they forgot it.']);
            }
            if (blank($student->student_number)) {
                throw ValidationException::withMessages(['student' => 'This learner has no Student ID yet, so a Student login cannot be used.']);
            }
            $hasActiveRecord = Enrollment::query()->where('student_id', $student->id)
                ->whereNotIn('status', Enrollment::TERMINAL_STATUSES)->exists();
            if (! $hasActiveRecord) {
                throw ValidationException::withMessages(['student' => 'Only a learner with a current application or enrollment can be given a Student account.']);
            }

            $access = $this->createTemporaryAccess->execute($student->full_name, null, null, 'student', $actor);
            $this->attachStudentUser->execute($student, $access->user, $actor, 'granted: '.trim($reason));

            return $access;
        });
    }
}
