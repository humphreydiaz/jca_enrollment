<?php

namespace App\Modules\People\Support;

use App\Models\User;
use App\Modules\People\Models\StudentProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StudentNumberLoginResolver
{
    public function resolve(string $identifier): ?User
    {
        $candidates = $this->candidates($identifier);

        return $candidates->count() === 1
            ? $candidates->first()
            : null;
    }

    public function candidates(string $identifier): Collection
    {
        $identifier = strtoupper(trim($identifier));

        if ($identifier === '') {
            return collect();
        }

        // Grade 7-10 Parent/Guardian login.
        if (preg_match('/^PAR-\d{2}-\d{3}$/', $identifier)) {
            if (
                ! Schema::hasTable('guardian_profiles')
                || ! Schema::hasColumn('guardian_profiles', 'parent_login_id')
            ) {
                return collect();
            }

            $userIds = DB::table('guardian_profiles')
                ->where('parent_login_id', $identifier)
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id');

            return $this->usersWithRole($userIds, 'guardian');
        }

        if (! StudentNumber::looksLike($identifier)) {
            return collect();
        }

        $number = StudentNumber::normalize($identifier);

        $students = StudentProfile::query()
            ->where('student_number', $number)
            ->limit(2)
            ->get();

        if ($students->count() !== 1) {
            return collect();
        }

        $student = $students->first();

        // The learner's own account, when one is connected (always for Grade 7-10; for
        // Nursery-Grade 6 only after the Registrar grants one on Family links).
        $studentAccounts = $student->user_id !== null
            ? $this->usersWithRole(collect([$student->user_id]), 'student')
            : collect();

        // Nursery-Grade 6: the managing parent signs in with the child's Student ID and their own
        // password, and keeps doing so after the child is granted an account. Parents who hold a
        // Grade 7-10 Parent ID (PAR-YY-NNN) use that instead. AuthenticateUserAction picks the one
        // candidate whose password matches and fails closed when two do.
        if (
            ! Schema::hasTable('guardian_student')
            || ! Schema::hasTable('guardian_profiles')
        ) {
            return $studentAccounts;
        }

        $guardianIds = DB::table('guardian_student as gs')
            ->join(
                'guardian_profiles as gp',
                'gp.id',
                '=',
                'gs.guardian_id'
            )
            ->where('gs.student_id', $student->id)
            ->whereNull('gs.ended_at')
            ->where('gs.may_manage_enrollment', 1)
            ->whereNotNull('gp.user_id')
            ->where(function ($query) {
                $query->whereNull('gp.parent_login_id')
                    ->orWhere('gp.parent_login_id', '');
            })
            ->distinct()
            ->pluck('gp.user_id');

        return $studentAccounts->merge($this->usersWithRole($guardianIds, 'guardian'))->unique('id')->values();
    }

    private function usersWithRole(
        Collection $userIds,
        string $role
    ): Collection {
        $ids = $userIds
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $ids)
            ->whereHas('roles', function ($query) use ($role) {
                $query->where('code', $role);
            })
            ->get();
    }
}
