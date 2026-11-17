<?php

namespace App\Modules\Communications\Gateways;

use App\Modules\Communications\Contracts\SmsGateway;
use App\Modules\Communications\Contracts\SmsGatewayResult;
use App\Modules\Communications\Enums\SmsDeliveryRoute;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class SemaphoreSmsGateway implements SmsGateway
{
    public function send(
        string $recipient,
        string $body,
        string $idempotencyKey,
        SmsDeliveryRoute $route = SmsDeliveryRoute::Standard,
        ?string $oneTimeCode = null,
    ): SmsGatewayResult {
        $key = (string) config('communications.sms.semaphore.api_key');
        $sender = trim((string) config('communications.sms.semaphore.sender_name'));
        if ($key === '') {
            return new SmsGatewayResult('disabled', null, 'missing_sms_configuration');
        }

        if ($route === SmsDeliveryRoute::Otp && ($oneTimeCode === null || ! str_contains($body, '{otp}'))) {
            return new SmsGatewayResult('failed', null, 'invalid_otp_request');
        }

        $endpoint = match ($route) {
            SmsDeliveryRoute::Priority => 'priority',
            SmsDeliveryRoute::Otp => 'otp',
            SmsDeliveryRoute::Standard => 'messages',
        };
        $parameters = [
            'apikey' => $key,
            // Semaphore expects local (09XXXXXXXXX) or 63-prefixed digits, not a leading "+".
            'number' => ltrim($recipient, '+'),
            'message' => $body,
        ];
        // D-070: until Semaphore approves a sender name, leave it out and Semaphore uses its default sender.
        if ($sender !== '') {
            $parameters['sendername'] = $sender;
        }
        if ($route === SmsDeliveryRoute::Otp) {
            $parameters['code'] = $oneTimeCode;
        }

        $response = Http::asForm()
            ->connectTimeout(3)
            ->timeout(10)
            // A second attempt covers short-lived network/provider failures. The
            // same application idempotency key is retained for local auditing.
            ->retry(2, 250, static function (Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError());
            }, throw: false)
            ->post(rtrim((string) config('communications.sms.semaphore.base_url'), '/').'/api/v4/'.$endpoint, $parameters);

        if (! $response->successful()) {
            return new SmsGatewayResult('failed', null, 'semaphore_http_'.$response->status());
        }

        $payload = $response->json();
        $reference = data_get($payload, '0.message_id') ?? data_get($payload, 'message_id');
        $providerStatus = str((string) (data_get($payload, '0.status') ?? data_get($payload, 'status')))->lower()->toString();

        if ($reference === null) {
            return new SmsGatewayResult('failed', null, 'semaphore_invalid_response');
        }

        if (in_array($providerStatus, ['failed', 'refunded'], true)) {
            return new SmsGatewayResult('failed', (string) $reference, 'semaphore_status_'.$providerStatus, $providerStatus);
        }

        return new SmsGatewayResult('accepted', is_scalar($reference) ? (string) $reference : null, null, $providerStatus !== '' ? $providerStatus : null);
    }
}
