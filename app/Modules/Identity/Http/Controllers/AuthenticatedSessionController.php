<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\AuthenticateUserAction;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthenticatedSessionController
{
    private const PORTAL_ROLES = ['guardian', 'student'];

    private const STAFF_ROLES = ['admin', 'registrar', 'accounting', 'teacher'];

    public function create(): View
    {
        return view('identity.auth.login');
    }

    public function createStaff(): View
    {
        return view('identity.auth.staff-login');
    }

    public function store(LoginRequest $request, AuthenticateUserAction $authenticateUser, AuditRecorder $auditRecorder): RedirectResponse
    {
        return $this->authenticate($request, $authenticateUser, $auditRecorder, self::PORTAL_ROLES);
    }

    public function storeStaff(LoginRequest $request, AuthenticateUserAction $authenticateUser, AuditRecorder $auditRecorder): RedirectResponse
    {
        return $this->authenticate($request, $authenticateUser, $auditRecorder, self::STAFF_ROLES);
    }

    /** @param list<string> $allowedRoles */
    private function authenticate(LoginRequest $request, AuthenticateUserAction $authenticateUser, AuditRecorder $auditRecorder, array $allowedRoles): RedirectResponse
    {
        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()
                ->withInput($request->safe()->only('identifier'))
                ->withErrors(['identifier' => 'Too many sign-in attempts. Please wait a minute before trying again.']);
        }

        $user = $authenticateUser->execute(
            $request->string('identifier')->toString(),
            $request->string('password')->toString(),
            $allowedRoles,
        );

        if ($user === null) {
            RateLimiter::hit($key, 60);

            return back()
                ->withInput($request->safe()->only('identifier'))
                ->withErrors(['identifier' => 'The provided credentials are not valid.']);
        }

        RateLimiter::clear($key);
        Auth::login($user);
        $request->session()->regenerate();
        $auditRecorder->record($user, 'identity.login.succeeded', $user);

        return redirect()->intended(
            $user->must_change_password
                ? route('identity.password.edit')
                : route('identity.dashboard'),
        );
    }

    public function destroy(Request $request, AuditRecorder $auditRecorder): RedirectResponse
    {
        $user = $request->user();
        $loginRoute = $user?->hasAnyRole(self::STAFF_ROLES)
            ? 'identity.staff.login'
            : 'identity.login';

        if ($user !== null) {
            $auditRecorder->record($user, 'identity.logout', $user);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($loginRoute);
    }

    private function throttleKey(Request $request): string
    {
        return hash('sha256', Str::lower($request->string('identifier')->toString()).'|'.$request->ip());
    }
}
