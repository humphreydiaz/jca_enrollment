<?php

namespace App\Modules\Communications\Gateways;

use App\Modules\Communications\Contracts\SmsGateway;
use App\Modules\Communications\Contracts\SmsGatewayResult;
use App\Modules\Communications\Enums\SmsDeliveryRoute;

class FakeSmsGateway implements SmsGateway
{
    /** @var list<array{recipient: string, body: string, idempotency_key: string, route: SmsDeliveryRoute, one_time_code: string|null}> */
    public array $sent = [];

    public bool $failNext = false;

    public function send(
        string $recipient,
        string $body,
        string $idempotencyKey,
        SmsDeliveryRoute $route = SmsDeliveryRoute::Standard,
        ?string $oneTimeCode = null,
    ): SmsGatewayResult {
        if ($this->failNext) {
            $this->failNext = false;

            return new SmsGatewayResult('failed', null, 'fake_failure');
        }

        $this->sent[] = compact('recipient', 'body', 'route') + [
            'idempotency_key' => $idempotencyKey,
            'one_time_code' => $oneTimeCode,
        ];

        return new SmsGatewayResult('accepted', 'fake-'.count($this->sent));
    }
}
