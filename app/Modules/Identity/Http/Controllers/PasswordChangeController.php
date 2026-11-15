<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\RevokeUserSessionsAction;
use App\Modules\Identity\Http\Requests\ChangePasswordRequest;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordChangeController
{
    public function edit(): View
    {
        return view('identity.auth.password-change');
    }

    public function update(
        ChangePasswordRequest $request,
        RevokeUserSessionsAction $revokeUserSessions,
        AuditRecorder $auditRecorder
    ): RedirectResponse {
        $user = $request->user();

        if (! Hash::check(
            $request->string('current_password')->toString(),
            $user->password
        )) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is not correct.',
            ]);
        }

        $user->forceFill([
            'password' => $request->string('password')->toString(),
            'must_change_password' => false,
            'password_changed_at' => now(),
            'temporary_access_expires_at' => null,
            'temporary_access_used_at' => null,
        ])->save();

        $revokeUserSessions->execute($user);

        Auth::login($user);
        $request->session()->regenerate();

        $auditRecorder->record(
            $user,
            'identity.password.changed',
            $user
        );

        return redirect()
            ->route('identity.dashboard')
            ->with(
                'status',
                'Password updated. Other sessions were signed out.'
            );
    }
}
