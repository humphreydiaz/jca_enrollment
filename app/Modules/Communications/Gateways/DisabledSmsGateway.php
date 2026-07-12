<?php

namespace App\Modules\Communications\Gateways;

use App\Modules\Communications\Contracts\SmsGateway;
use App\Modules\Communications\Contracts\SmsGatewayResult;

class DisabledSmsGateway implements SmsGateway
{
    public function send(string $recipient, string $body, string $idempotencyKey): SmsGatewayResult
    {
        return new SmsGatewayResult('disabled', null, 'sms_disabled');
    }
}
