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
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class UserManagementController
{
    public function index(
        ListUsersRequest $request,
        ListUsersAction $listUsers
    ): View {
        return view(
            'identity.admin.users',
            $listUsers->execute($request->validated())
        );
    }

    public function store(
        CreateUserRequest $request,
        CreateInvitedUserAction $createInvitedUser,
        SendInvitationSmsAction $sendInvitationSms
    ): RedirectResponse {

        // Only staff accounts can be created here.
        $validated = $request->validated();

        Validator::make($validated, [
            'roles' => ['required', 'array', 'size:1'],
            'roles.*' => [
                'required',
                'string',
                'in:admin,registrar,accounting,teacher',
            ],
            'employed_on' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
        ], [
            'roles.required' => 'Please select a staff role.',
            'roles.size' => 'Only one staff role can be selected.',
            'roles.*.in' => 'Only staff roles are allowed.',
            'employed_on.required' => 'Employment start date is required.',
        ])->validate();

        $channel = $validated['channel'];

        $sentTo = $channel === 'email'
            ? $validated['email']
            : $validated['mobile'];

        $created = $createInvitedUser->execute(
            $validated,
            $validated['roles'],
            $channel,
            $sentTo,
            $request->user(),
        );

        $setupUrl = route('identity.invitations.short', [
            'token' => $created->invitation->token,
        ]);

        $smsSent = $channel === 'mobile'
            && $sendInvitationSms->execute(
                $created->invitation->invitation,
                (string) $validated['mobile'],
                $created->invitation->token
            );

        $status = $smsSent
            ? 'Staff invitation created. The SMS service accepted the one-time code for delivery; this does not confirm receipt by the phone. A fallback code and link are shown below.'
            : 'Staff invitation created, but the SMS service did not accept the one-time code. Use the fallback code or link shown below.';

        return redirect()
            ->route('identity.admin.users.index')
            ->with('status', $status)
            ->with('invitation_code', $created->invitation->token)
            ->with('invitation_url', $setupUrl);
    }

    public function updateRoles(
        UpdateUserRolesRequest $request,
        User $user,
        AssignRolesAction $assignRoles
    ): RedirectResponse {
        abort(403, 'Account role editing is disabled.');
    }

    public function disable(
        User $user,
        DisableUserAction $disableUser,
        DisableUserRequest $request
    ): RedirectResponse {

        abort_if(
            $user->is($request->user()),
            422,
            'You cannot disable your own account.'
        );

        $disableUser->execute(
            $user,
            $request->user(),
            $request->validated('reason')
        );

        return back()->with(
            'status',
            'Account disabled and active sessions revoked.'
        );
    }

    public function reactivate(
        User $user,
        ReactivateUserRequest $request,
        ReactivateUserAction $reactivateUser
    ): RedirectResponse {
        $result = $reactivateUser->execute(
            $user,
            $request->user(),
            $request->validated('reason')
        );

        if ($result['type'] === 'staff') {
            return back()
                ->with(
                    'status',
                    'Staff account reactivated. Deliver the new one-time setup link through the verified channel.'
                )
                ->with(
                    'invitation_url',
                    route('identity.invitations.short', [
                        'token' => $result['invitation']->token,
                    ])
                );
        }

        return back()->with(
            'status',
            'Account reactivated. Temporary Password: '
            .$result['temporary_password']
            .' | Expires: '
            .$result['expires_at']->format('Y-m-d H:i')
            .' | Deliver privately to the verified account owner. Password change is required on first login.'
        );
    }

    public function issueRecoveryInvitation(
        User $user,
        IssueRecoveryInvitationRequest $request,
        CreateAccountInvitationAction $createInvitation
    ): RedirectResponse {

        $channel = $user->email !== null
            ? 'email'
            : 'mobile';

        $sentTo = $channel === 'email'
            ? $user->email
            : $user->mobile;

        abort_if(
            $sentTo === null,
            422,
            'This account has no verified contact channel.'
        );

        $created = $createInvitation->execute(
            $user,
            $channel,
            $sentTo,
            $request->user(),
            'account_recovery'
        );

        return back()
            ->with(
                'status',
                'Recovery invitation created. Verify identity before delivering the one-time link.'
            )
            ->with(
                'invitation_url',
                route('identity.invitations.short', [
                    'token' => $created->token,
                ])
            );
    }
}
