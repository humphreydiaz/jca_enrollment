<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\People\Support\StaffNumber;
use App\Modules\People\Support\StaffNumberLoginResolver;
use App\Modules\People\Support\StudentNumber;
use App\Modules\People\Support\StudentNumberLoginResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class AuthenticateUserAction
{
    private const MAX_CANDIDATES = 10;

    public function __construct(
        private StudentNumberLoginResolver $studentNumberLogin,
        private StaffNumberLoginResolver $staffNumberLogin,
    ) {}

    public function execute(
        string $identifier,
        string $password
    ): ?User {
        $candidates = $this->candidates($identifier);

        // Reject missing or ambiguous account identifiers.
        if (
            $candidates->isEmpty()
            || $candidates->count() > self::MAX_CANDIDATES
        ) {
            return null;
        }

        $authenticatedUser = null;

        foreach ($candidates as $user) {
            if (
                $user->password === null
                || ! $user->isActive()
            ) {
                continue;
            }

            if (
                $user->must_change_password
                && $user->temporary_access_expires_at !== null
                && $user->temporary_access_expires_at->isPast()
            ) {
                continue;
            }

            if (
                $user->must_change_password
                && $user->temporary_access_used_at !== null
            ) {
                continue;
            }

            if (! Hash::check($password, $user->password)) {
                continue;
            }

            if ($authenticatedUser !== null) {
                return null;
            }

            $authenticatedUser = $user;
        }

        if ($authenticatedUser === null) {
            return null;
        }

        if (
            $authenticatedUser->must_change_password
            && $authenticatedUser->temporary_access_expires_at !== null
        ) {
            $usedAt = now();

            $claimed = User::query()
                ->whereKey($authenticatedUser->id)
                ->whereNull('temporary_access_used_at')
                ->update([
                    'temporary_access_used_at' => $usedAt,
                    'last_login_at' => $usedAt,
                ]);

            if ($claimed !== 1) {
                return null;
            }

            $authenticatedUser->temporary_access_used_at = $usedAt;
            $authenticatedUser->last_login_at = $usedAt;
        } else {
            $authenticatedUser->forceFill([
                'last_login_at' => now(),
            ])->save();
        }

        return $authenticatedUser;
    }

    /**
     * Resolve Student, Parent/Guardian, and Staff login IDs.
     *
     * @return Collection<int, User>
     */
    private function candidates(string $identifier): Collection
    {
        $identifier = strtoupper(trim($identifier));

        // Student and Parent/Guardian IDs.
        if (
            StudentNumber::looksLike($identifier)
            || preg_match('/^PAR-\d{2}-\d{3}$/', $identifier)
        ) {
            return $this->studentNumberLogin
                ->candidates($identifier);
        }

        // Staff IDs.
        if (StaffNumber::looksLike($identifier)) {
            $user = $this->staffNumberLogin
                ->resolve($identifier);

            return $user === null
                ? collect()
                : collect([$user]);
        }

        return collect();
    }
}
