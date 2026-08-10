<?php

namespace App\Modules\Communications\Gateways;

use App\Modules\Communications\Contracts\SmsGateway;
use App\Modules\Communications\Contracts\SmsGatewayResult;
use Illuminate\Support\Facades\Http;

class SemaphoreSmsGateway implements SmsGateway
{
    public function send(string $recipient, string $body, string $idempotencyKey): SmsGatewayResult
    {
        $key = (string) config('communications.sms.semaphore.api_key');
        $sender = (string) config('communications.sms.semaphore.sender_name');
        if ($key === '' || $sender === '') {
            return new SmsGatewayResult('disabled', null, 'missing_sms_configuration');
        }

        $response = Http::asForm()->timeout(10)->post(rtrim((string) config('communications.sms.semaphore.base_url'), '/').'/api/v4/messages', [
            'apikey' => $key,
            // Semaphore expects local (09XXXXXXXXX) or 63-prefixed digits, not a leading "+".
            'number' => ltrim($recipient, '+'),
            'message' => $body,
            'sendername' => $sender,
        ]);

        if (! $response->successful()) {
            return new SmsGatewayResult('failed', null, 'semaphore_http_'.$response->status());
        }

        $reference = data_get($response->json(), '0.message_id') ?? data_get($response->json(), 'message_id');

        return new SmsGatewayResult('accepted', is_scalar($reference) ? (string) $reference : null);
    }
}
