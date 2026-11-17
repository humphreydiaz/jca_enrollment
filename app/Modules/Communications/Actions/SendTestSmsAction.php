<?php

namespace App\Modules\Communications\Actions;

use App\Models\User;
use App\Modules\Communications\Contracts\SmsGateway;
use App\Modules\Communications\Contracts\SmsGatewayResult;
use App\Modules\Communications\Support\SmsLength;
use App\Modules\Communications\Support\SmsRecipient;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * D-070: the Admin sends one fixed test text to a number they type, straight through the configured gateway,
 * and sees what the provider answered. The full number is never stored; the audit keeps it masked.
 */
class SendTestSmsAction
{
    public const MESSAGE = 'JCSGO Christian Academy: this is a test message from the school system. SMS is working.';

    public function __construct(private readonly SmsGateway $gateway, private readonly AuditRecorder $audit) {}

    public function execute(string $mobile, User $actor): SmsGatewayResult
    {
        abort_unless($actor->hasRole('admin'), 403);

        $result = $this->gateway->send($mobile, self::MESSAGE, 'test-'.Str::ulid(), SmsLength::route());
        Cache::forget('communications.semaphore.balance');

        $this->audit->record($actor, 'communications.sms.test_sent', null, [
            'recipient' => (new SmsRecipient('user', 0, null, '', $mobile, ''))->maskedMobile(),
            'status' => $result->status,
            'failure_code' => $result->failureCode,
        ]);

        return $result;
    }
}
