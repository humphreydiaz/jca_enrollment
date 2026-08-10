<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthenticateUserAction
{
    /** @param list<string> $allowedRoles */
    public function execute(string $identifier, string $password, array $allowedRoles = []): ?User
    {
        $email = ContactNormalizer::email($identifier);
        $mobile = ContactNormalizer::mobile($identifier);

        if ($email === null && $mobile === null) {
            return null;
        }

        $user = User::query()
            ->where(function ($query) use ($email, $mobile): void {
                if ($email !== null) {
                    $query->where('email', $email);
                }

                if ($mobile !== null) {
                    $query->orWhere('mobile', $mobile);
                }
            })
            ->first();

        if ($user === null || $user->password === null || ! $user->isActive()) {
            return null;
        }

        if (! Hash::check($password, $user->password)) {
            return null;
        }

        if ($allowedRoles !== [] && ! $user->hasAnyRole($allowedRoles)) {
            return null;
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $user;
    }
}
