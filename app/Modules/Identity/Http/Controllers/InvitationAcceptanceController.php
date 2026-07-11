<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\RedeemInvitationAction;
use App\Modules\Identity\Http\Requests\InvitationPasswordRequest;
use App\Modules\Identity\Models\AccountInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvitationAcceptanceController
{
    public function create(string $invitation, string $token): View|Response
    {
        $resolvedInvitation = $this->resolve($invitation, $token);

        if ($resolvedInvitation === null) {
            return response()->view('identity.auth.invitation-invalid', status: 410);
        }

        return view('identity.auth.invitation', ['invitation' => $resolvedInvitation, 'token' => $token]);
    }

    public function store(InvitationPasswordRequest $request, string $invitation, string $token, RedeemInvitationAction $redeemInvitation): RedirectResponse
    {
        $resolvedInvitation = $this->resolve($invitation, $token);

        if ($resolvedInvitation === null) {
            return redirect()->route('identity.login')->withErrors([
                'identifier' => 'This account invitation is invalid or has expired.',
            ]);
        }

        $redeemInvitation->execute(
            $resolvedInvitation,
            $token,
            $request->string('password')->toString(),
        );

        Auth::login($resolvedInvitation->fresh()->user);
        $request->session()->regenerate();

        return redirect()->route('identity.dashboard')->with('status', 'Your account is ready.');
    }

    private function resolve(string $publicId, string $token): ?AccountInvitation
    {
        $invitation = AccountInvitation::query()
            ->where('public_id', $publicId)
            ->where('token_hash', hash('sha256', $token))
            ->first();

        return $invitation !== null && $invitation->isUsable() ? $invitation : null;
    }
}
