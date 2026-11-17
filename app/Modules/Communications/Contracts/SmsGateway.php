<?php

namespace App\Modules\Communications\Contracts;

use App\Modules\Communications\Enums\SmsDeliveryRoute;

interface SmsGateway
{
    public function send(
        string $recipient,
        string $body,
        string $idempotencyKey,
        SmsDeliveryRoute $route = SmsDeliveryRoute::Standard,
        ?string $oneTimeCode = null,
    ): SmsGatewayResult;
}
