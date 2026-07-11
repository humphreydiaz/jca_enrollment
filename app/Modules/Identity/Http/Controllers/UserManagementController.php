<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Models\User;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Actions\CreateAccountInvitationAction;
use App\Modules\Identity\Actions\CreateInvitedUserAction;
use App\Modules\Identity\Actions\DisableUserAction;
use App\Modules\Identity\Http\Requests\CreateUserRequest;
use App\Modules\Identity\Http\Requests\DisableUserRequest;
use App\Modules\Identity\Http\Requests\IssueRecoveryInvitationRequest;
use App\Modules\Identity\Http\Requests\UpdateUserRolesRequest;
use App\Modules\Identity\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserManagementController
{
    public function index(): View
    {
        return view('identity.admin.users', [
            'users' => User::query()->with('roles')->orderBy('name')->paginate(15),
            'roles' => Role::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(CreateUserRequest $request, CreateInvitedUserAction $createInvitedUser): RedirectResponse
    {
        $validated = $request->validated();
        $channel = $validated['channel'];
        $sentTo = $channel === 'email' ? $validated['email'] : $validated['mobile'];
        $created = $createInvitedUser->execute(
            $validated,
            $validated['roles'],
            $channel,
            $sentTo,
            $request->user(),
        );

        return redirect()
            ->route('identity.admin.users.index')
            ->with('status', 'Account invitation created. Deliver the one-time link through the verified contact channel.')
            ->with('invitation_url', route('identity.invitations.show', [
                'invitation' => $created->invitation->invitation->public_id,
                'token' => $created->invitation->token,
            ]));
    }

    public function updateRoles(UpdateUserRolesRequest $request, User $user, AssignRolesAction $assignRoles): RedirectResponse
    {
        $assignRoles->execute($user, $request->validated('roles'), $request->user());

        return back()->with('status', 'Account roles updated and active sessions re-evaluated.');
    }

    public function disable(User $user, DisableUserAction $disableUser, DisableUserRequest $request): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'You cannot disable your own account.');
        $disableUser->execute($user, $request->user());

        return back()->with('status', 'Account disabled and active sessions revoked.');
    }

    public function issueRecoveryInvitation(User $user, IssueRecoveryInvitationRequest $request, CreateAccountInvitationAction $createInvitation): RedirectResponse
    {
        $channel = $user->email !== null ? 'email' : 'mobile';
        $sentTo = $channel === 'email' ? $user->email : $user->mobile;

        abort_if($sentTo === null, 422, 'This account has no verified contact channel.');

        $created = $createInvitation->execute($user, $channel, $sentTo, $request->user(), 'account_recovery');

        return back()
            ->with('status', 'Recovery invitation created. Verify identity before delivering the one-time link.')
            ->with('invitation_url', route('identity.invitations.show', [
                'invitation' => $created->invitation->public_id,
                'token' => $created->token,
            ]));
    }
}
