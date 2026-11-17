<?php

namespace App\Modules\Communications\Http\Controllers;

use App\Models\User;
use App\Modules\Communications\Actions\UpdateSmsConsentAction;
use App\Modules\Communications\Http\Requests\UpdateSmsConsentRequest;
use App\Modules\Communications\Queries\SmsRecipientDirectory;
use App\Modules\Communications\Support\SmsRecipient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * D-052: SMS preferences for everyone with a number on file. A person may hold more than one
 * number here (a teacher who is also a parent), and each is opted in or out on its own.
 */
class ContactPreferenceController
{
    public function edit(Request $request): View
    {
        return view('communications.preferences.edit', ['numbers' => $this->numbers($request->user())]);
    }

    public function update(UpdateSmsConsentRequest $request, UpdateSmsConsentAction $action): RedirectResponse
    {
        $choices = (array) $request->validated('announcement', []);
        foreach ($this->numbers($request->user()) as $key => $number) {
            if ($number['owner'] !== null) {
                $action->execute($number['owner'], 'announcement', (bool) ($choices[$key] ?? false), $request->user(), 'portal_preferences');
            }
        }

        return back()->with('status', 'SMS preferences saved.');
    }

    /** @return array<string, array{label: string, owner: mixed, mobile: ?string, granted: bool}> */
    private function numbers(User $user): array
    {
        $numbers = [];
        $mask = fn (?string $mobile) => blank($mobile) ? null : (new SmsRecipient('user', 0, null, '', (string) $mobile, ''))->maskedMobile();
        if ($user->hasRole('guardian') && $user->guardianProfile !== null) {
            $numbers['guardian'] = ['label' => 'As a parent/guardian', 'owner' => $user->guardianProfile, 'mobile' => $mask($user->guardianProfile->mobile)];
        }
        if ($user->hasRole('student') && $user->studentProfile !== null) {
            $numbers['student'] = ['label' => 'As a student', 'owner' => $user->studentProfile, 'mobile' => $mask($user->studentProfile->mobile)];
        }
        if ($user->hasAnyRole(SmsRecipientDirectory::STAFF_ROLES)) {
            $numbers['staff'] = ['label' => 'As school staff', 'owner' => $user, 'mobile' => $mask($user->mobile)];
        }

        return array_map(fn (array $number) => [...$number, 'granted' => UpdateSmsConsentAction::granted($number['owner'])], $numbers);
    }
}
