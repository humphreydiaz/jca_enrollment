<?php

namespace App\Modules\Communications\Contracts;

interface SmsGateway
{
    public function send(string $recipient, string $body, string $idempotencyKey): SmsGatewayResult;
}
