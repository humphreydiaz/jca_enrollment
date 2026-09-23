<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\People\Support\StudentNumber;
use App\Modules\People\Support\StudentNumberLoginResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class AuthenticateUserAction
{
    /**
     * Upper bound on accounts examined for one identifier, so a heavily shared
     * contact number can never turn a sign-in into an expensive hash loop.
     */
    private const MAX_CANDIDATES = 10;

    public function __construct(private StudentNumberLoginResolver $studentNumberLogin) {}

    /** @param list<string> $allowedRoles */
    public function execute(string $identifier, string $password, array $allowedRoles = []): ?User
    {
        // A household may share one mobile number between a parent and a child,
        // so an identifier can match several accounts. The password decides
        // which of them is signing in; stopping at the first match would lock
        // everyone but that account out.
        foreach ($this->candidates($identifier) as $user) {
            if ($user->password === null || ! $user->isActive()) {
                continue;
            }

            if (! Hash::check($password, $user->password)) {
                continue;
            }

            if ($allowedRoles !== [] && ! $user->hasAnyRole($allowedRoles)) {
                continue;
            }

            $user->forceFill(['last_login_at' => now()])->save();

            return $user;
        }

        return null;
    }

    /**
     * Accounts that could be behind this identifier. A learner may sign in with
     * their student number; parents, students, and staff may still use the
     * email address or mobile number on their account.
     *
     * @return Collection<int, User>
     */
    private function candidates(string $identifier): Collection
    {
        if (StudentNumber::looksLike($identifier)) {
            $user = $this->studentNumberLogin->resolve($identifier);

            return $user === null ? collect() : collect([$user]);
        }

        $email = ContactNormalizer::email($identifier);
        $mobile = ContactNormalizer::mobile($identifier);

        if ($email === null && $mobile === null) {
            return collect();
        }

        return User::query()
            ->where(function ($query) use ($email, $mobile): void {
                if ($email !== null) {
                    $query->where('email', $email);
                }

                if ($mobile !== null) {
                    $query->orWhere('mobile', $mobile);
                }
            })
            ->orderBy('id')
            ->limit(self::MAX_CANDIDATES)
            ->get();
    }
}
