<?php

namespace App\Modules\Communications\Gateways;

use App\Modules\Communications\Contracts\SmsGateway;
use App\Modules\Communications\Contracts\SmsGatewayResult;
use App\Modules\Communications\Enums\SmsDeliveryRoute;

class DisabledSmsGateway implements SmsGateway
{
    public function send(
        string $recipient,
        string $body,
        string $idempotencyKey,
        SmsDeliveryRoute $route = SmsDeliveryRoute::Standard,
        ?string $oneTimeCode = null,
    ): SmsGatewayResult {
        return new SmsGatewayResult('disabled', null, 'sms_disabled');
    }
}
