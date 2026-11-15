<?php

namespace App\Modules\People\Support;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use Illuminate\Support\Facades\Hash;

/**
 * A parent and child can both sign in with the child's Student ID; the password tells them
 * apart, and a password shared by two such accounts fails closed (neither can sign in). This
 * guard stops an owner from choosing a password already used by another account that signs
 * in with one of the same Student IDs (D-048).
 */
class SharedStudentIdPasswordGuard
{
    public function __construct(private readonly StudentNumberLoginResolver $resolver) {}

    public function collides(User $user, string $password): bool
    {
        $studentIds = StudentProfile::query()->where('user_id', $user->id)->pluck('id')
            ->merge(Enrollment::query()->where('student_applicant_user_id', $user->id)->pluck('student_id'))
            ->merge(GuardianProfile::query()->where('user_id', $user->id)->with('studentLinks')->get()
                ->flatMap(fn (GuardianProfile $guardian) => $guardian->studentLinks->whereNull('ended_at')->pluck('student_id')))
            ->unique();

        $numbers = StudentProfile::query()->whereIn('id', $studentIds)->whereNotNull('student_number')->pluck('student_number');

        foreach ($numbers as $number) {
            foreach ($this->resolver->candidates($number) as $other) {
                if ($other->id !== $user->id && $other->password !== null && Hash::check($password, $other->password)) {
                    return true;
                }
            }
        }

        return false;
    }
}
