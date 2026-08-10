<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Models\User;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Actions\CreateAccountInvitationAction;
use App\Modules\Identity\Actions\CreateInvitedUserAction;
use App\Modules\Identity\Actions\DisableUserAction;
use App\Modules\Identity\Actions\ListUsersAction;
use App\Modules\Identity\Actions\ReactivateUserAction;
use App\Modules\Identity\Actions\SendInvitationSmsAction;
use App\Modules\Identity\Http\Requests\CreateUserRequest;
use App\Modules\Identity\Http\Requests\DisableUserRequest;
use App\Modules\Identity\Http\Requests\IssueRecoveryInvitationRequest;
use App\Modules\Identity\Http\Requests\ListUsersRequest;
use App\Modules\Identity\Http\Requests\ReactivateUserRequest;
use App\Modules\Identity\Http\Requests\UpdateUserRolesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserManagementController
{
    public function index(ListUsersRequest $request, ListUsersAction $listUsers): View
    {
        return view('identity.admin.users', $listUsers->execute($request->validated()));
    }

    public function store(CreateUserRequest $request, CreateInvitedUserAction $createInvitedUser, SendInvitationSmsAction $sendInvitationSms): RedirectResponse
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

        $setupUrl = route('identity.invitations.show', [
            'invitation' => $created->invitation->invitation->public_id,
            'token' => $created->invitation->token,
        ]);

        // Auto-deliver the one-time setup link by SMS when the admin chose the mobile channel.
        $smsSent = $channel === 'mobile'
            && $sendInvitationSms->execute($created->invitation->invitation, (string) $validated['mobile'], $setupUrl);

        $status = $smsSent
            ? 'Account invitation created. The one-time setup link was sent by SMS. A copy is shown below as a fallback.'
            : 'Account invitation created. Deliver the one-time link through the verified contact channel.';

        return redirect()
            ->route('identity.admin.users.index')
            ->with('status', $status)
            ->with('invitation_url', $setupUrl);
    }

    public function updateRoles(UpdateUserRolesRequest $request, User $user, AssignRolesAction $assignRoles): RedirectResponse
    {
        $assignRoles->execute($user, $request->validated('roles'), $request->user());

        return back()->with('status', 'Account roles updated and active sessions re-evaluated.');
    }

    public function disable(User $user, DisableUserAction $disableUser, DisableUserRequest $request): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'You cannot disable your own account.');
        $disableUser->execute($user, $request->user(), $request->validated('reason'));

        return back()->with('status', 'Account disabled and active sessions revoked.');
    }

    public function reactivate(User $user, ReactivateUserRequest $request, ReactivateUserAction $reactivateUser): RedirectResponse
    {
        $created = $reactivateUser->execute(
            $user,
            $request->user(),
            $request->validated('reason'),
        );

        return back()
            ->with('status', 'Account reactivated. Deliver the new one-time setup link through the verified channel.')
            ->with('invitation_url', route('identity.invitations.show', [
                'invitation' => $created->invitation->public_id,
                'token' => $created->token,
            ]));
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
