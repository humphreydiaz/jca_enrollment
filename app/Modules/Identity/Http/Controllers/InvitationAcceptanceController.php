<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\RedeemInvitationAction;
use App\Modules\Identity\Http\Requests\ActivateAccountRequest;
use App\Modules\Identity\Http\Requests\InvitationPasswordRequest;
use App\Modules\Identity\Models\AccountInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvitationAcceptanceController
{
    public function activation(): View
    {
        return view('identity.auth.activate');
    }

    public function activate(ActivateAccountRequest $request): RedirectResponse
    {
        $token = $request->validated('code');

        if ($this->resolveByToken($token) === null) {
            return back()->withInput()->withErrors([
                'code' => 'This account code is invalid, expired, already used, or replaced. Ask the school for help if needed.',
            ]);
        }

        return redirect()->route('identity.invitations.short', ['token' => $token]);
    }

    public function create(string $invitation, string $token): View|Response
    {
        $resolvedInvitation = $this->resolve($invitation, $token);

        if ($resolvedInvitation === null) {
            return response()->view('identity.auth.invitation-invalid', status: 410);
        }

        return view('identity.auth.invitation', ['invitation' => $resolvedInvitation, 'token' => $token]);
    }

    public function createShort(string $token): View|Response
    {
        $resolvedInvitation = $this->resolveByToken($token);

        if ($resolvedInvitation === null) {
            return response()->view('identity.auth.invitation-invalid', status: 410);
        }

        return view('identity.auth.invitation', ['invitation' => $resolvedInvitation, 'token' => $token]);
    }

    public function store(InvitationPasswordRequest $request, string $invitation, string $token, RedeemInvitationAction $redeemInvitation): RedirectResponse
    {
        $resolvedInvitation = $this->resolve($invitation, $token);

        return $this->redeem($resolvedInvitation, $token, $request, $redeemInvitation);
    }

    public function storeShort(InvitationPasswordRequest $request, string $token, RedeemInvitationAction $redeemInvitation): RedirectResponse
    {
        return $this->redeem($this->resolveByToken($token), $token, $request, $redeemInvitation);
    }

    private function redeem(?AccountInvitation $invitation, string $token, InvitationPasswordRequest $request, RedeemInvitationAction $redeemInvitation): RedirectResponse
    {
        if ($invitation === null) {
            return redirect()->route('identity.login')->withErrors([
                'identifier' => 'This account invitation is invalid or has expired.',
            ]);
        }

        $redeemInvitation->execute(
            $invitation,
            $token,
            $request->string('password')->toString(),
        );

        Auth::login($invitation->fresh()->user);
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

    private function resolveByToken(string $token): ?AccountInvitation
    {
        $invitation = AccountInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        return $invitation !== null && $invitation->isUsable() ? $invitation : null;
    }
}
