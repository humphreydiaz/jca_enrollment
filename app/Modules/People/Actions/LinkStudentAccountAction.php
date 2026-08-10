<?php

namespace App\Modules\People\Actions;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Models\StudentProfile;
use App\Modules\People\Support\StudentAccountIdentityMatcher;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LinkStudentAccountAction
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly StudentAccountIdentityMatcher $identityMatcher,
    ) {}

    public function execute(StudentProfile $student, User $account, User $actor, ?string $exceptionReason = null): StudentProfile
    {
        return DB::transaction(function () use ($account, $actor, $exceptionReason, $student): StudentProfile {
            $lockedStudent = StudentProfile::query()->lockForUpdate()->findOrFail($student->id);
            $lockedAccount = User::query()->lockForUpdate()->findOrFail($account->id);

            if (! $lockedAccount->hasRole('student')) {
                throw ValidationException::withMessages([
                    'user_public_id' => 'Select an account with the Student role.',
                ]);
            }

            if (! $this->identityMatcher->matches($lockedStudent, $lockedAccount)) {
                throw ValidationException::withMessages([
                    'user_public_id' => "The selected login belongs to {$lockedAccount->name}, but this student profile is {$lockedStudent->full_name}. Create or select the matching student profile instead.",
                ]);
            }

            if ($lockedStudent->user_id !== null && $lockedStudent->user_id !== $lockedAccount->id) {
                throw ValidationException::withMessages([
                    'user_public_id' => 'This student profile is already connected to another account.',
                ]);
            }

            $existingProfile = StudentProfile::query()
                ->where('user_id', $lockedAccount->id)
                ->whereKeyNot($lockedStudent->id)
                ->lockForUpdate()
                ->exists();

            if ($existingProfile) {
                throw ValidationException::withMessages([
                    'user_public_id' => 'This Student account is already connected to another student profile.',
                ]);
            }

            $approvedEnrollment = Enrollment::query()
                ->with('gradeLevel')
                ->where('student_id', $lockedStudent->id)
                ->whereIn('status', ['approved', 'enrolled'])
                ->orderByDesc('approved_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($approvedEnrollment === null) {
                throw ValidationException::withMessages([
                    'user_public_id' => 'A Student account can be connected only after this student has an approved or enrolled record.',
                ]);
            }

            $isHighSchool = $approvedEnrollment->gradeLevel?->allowsStudentAccountByDefault() === true;

            if (! $isHighSchool && (blank($exceptionReason) || mb_strlen(trim($exceptionReason)) < 10 || in_array(strtolower(trim($exceptionReason)), ['n/a', 'na', 'none'], true))) {
                throw ValidationException::withMessages([
                    'exception_reason' => 'Pre-elementary and Grades 1-6 use guardian access by default. Enter the approved exception reason to connect a Student account.',
                ]);
            }

            if ($lockedStudent->user_id === $lockedAccount->id) {
                return $lockedStudent;
            }

            $lockedStudent->update(['user_id' => $lockedAccount->id]);

            $this->auditRecorder->record($actor, 'people.student.account_linked', $lockedStudent, [
                'linked_user_public_id' => $lockedAccount->public_id,
                'enrollment_public_id' => $approvedEnrollment->public_id,
                'grade_code' => $approvedEnrollment->gradeLevel?->code,
                'is_elementary_exception' => ! $isHighSchool,
                'exception_reason' => $isHighSchool ? null : $exceptionReason,
            ]);

            return $lockedStudent->fresh();
        });
    }
}
