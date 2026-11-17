<?php

namespace App\Modules\Communications\Http\Controllers;

use App\Modules\Communications\Actions\SendTestSmsAction;
use App\Modules\Communications\Http\Requests\SendTestSmsRequest;
use Illuminate\Http\RedirectResponse;

/** D-070: Admin "Send a test text" from the SMS status panel. */
class SmsTestController
{
    public function __invoke(SendTestSmsRequest $request, SendTestSmsAction $send): RedirectResponse
    {
        $result = $send->execute($request->mobile(), $request->user());

        $message = match (true) {
            $result->status === 'accepted' => 'Test text accepted by Semaphore. It should arrive within a minute.',
            $result->status === 'logged' => 'SMS is in test mode: the text was recorded but not sent to a phone.',
            $result->failureCode === 'missing_sms_configuration' || $result->status === 'disabled' => 'Live SMS is off, so nothing was sent. Turn it on in the server .env (see the steps below).',
            default => 'Semaphore did not accept the test text ('.$result->failureCode.'). Check the API key, sender name and credit balance.',
        };

        return $result->accepted() && $result->status === 'accepted'
            ? back()->with('status', $message)
            : back()->withErrors(['test_mobile' => $message])->withInput();
    }
}
