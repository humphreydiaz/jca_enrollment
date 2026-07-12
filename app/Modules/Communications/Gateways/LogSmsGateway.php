<?php

namespace App\Modules\Communications\Gateways;

use App\Modules\Communications\Contracts\SmsGateway;
use App\Modules\Communications\Contracts\SmsGatewayResult;

class LogSmsGateway implements SmsGateway
{
    public function send(string $recipient, string $body, string $idempotencyKey): SmsGatewayResult
    {
        // Deliberately do not write the recipient or message body to application logs.
        return new SmsGatewayResult('logged', 'local-'.substr(hash('sha256', $idempotencyKey), 0, 16));
    }
}
