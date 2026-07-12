<?php

namespace App\Modules\Communications\Gateways;

use App\Modules\Communications\Contracts\SmsGateway;
use App\Modules\Communications\Contracts\SmsGatewayResult;

class FakeSmsGateway implements SmsGateway
{
    /** @var list<array{recipient: string, body: string, idempotency_key: string}> */
    public array $sent = [];

    public bool $failNext = false;

    public function send(string $recipient, string $body, string $idempotencyKey): SmsGatewayResult
    {
        if ($this->failNext) {
            $this->failNext = false;

            return new SmsGatewayResult('failed', null, 'fake_failure');
        }

        $this->sent[] = compact('recipient', 'body', 'idempotencyKey') + ['idempotency_key' => $idempotencyKey];

        return new SmsGatewayResult('accepted', 'fake-'.count($this->sent));
    }
}
