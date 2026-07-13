<?php

namespace App\Modules\Communications\Http\Controllers;

use App\Modules\Communications\Actions\UpdateSmsConsentAction;
use App\Modules\Communications\Http\Requests\UpdateSmsConsentRequest;
use App\Modules\Communications\Models\SmsConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactPreferenceController
{
    public function edit(Request $request): View
    {
        $guardian = $request->user()->guardianProfile;
        abort_unless($guardian !== null, 403);

        return view('communications.preferences.edit', ['guardian' => $guardian, 'consents' => SmsConsent::query()->where('guardian_id', $guardian->id)->get()->keyBy('purpose')]);
    }

    public function update(UpdateSmsConsentRequest $request, UpdateSmsConsentAction $action): RedirectResponse
    {
        $guardian = $request->user()->guardianProfile;
        abort_unless($guardian !== null, 403);
        foreach (['transactional', 'announcement'] as $purpose) {
            $action->execute($guardian, $purpose, $request->boolean($purpose), $request->user());
        }

        return back()->with('success', 'SMS preferences updated.');
    }
}
