<?php

namespace App\Modules\Enrollment\Actions;

use App\Modules\Communications\Contracts\SmsGateway;
use App\Modules\Communications\Contracts\SmsGatewayResult;
use App\Modules\Communications\Enums\SmsDeliveryRoute;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\ContactNormalizer;
use App\Modules\Shared\Audit\AuditRecorder;
use Throwable;

class SendContactVerificationSmsAction
{
    public function __construct(
        private readonly SmsGateway $gateway,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * Deliver the six-digit enrollment contact verification code over SMS.
     *
     * This is a transactional, pre-account security message sent directly through
     * the gateway. A delivery failure never breaks enrollment start.
     */
    public function execute(Enrollment $enrollment, string $mobile, string $code): bool
    {
        $normalized = ContactNormalizer::mobile($mobile);
        if ($normalized === null) {
            return false;
        }

        $body = 'JCSGOCA verification code: {otp}. Expires in 15 minutes. Do not share.';

        try {
            $result = $this->gateway->send(
                $normalized,
                $body,
                'enrollment-verify:'.$enrollment->id,
                SmsDeliveryRoute::Otp,
                $code,
            );
        } catch (Throwable $e) {
            $result = new SmsGatewayResult('failed', null, 'gateway_exception');
        }

        $status = $result->accepted() ? 'accepted' : ($result->status === 'disabled' ? 'disabled' : 'failed');
        $this->audit->record(null, 'enrollment.contact.verification.sms.'.$status, $enrollment, [
            'channel' => 'mobile',
            'provider' => (string) config('communications.sms.driver'),
            'route' => SmsDeliveryRoute::Otp->value,
            'provider_reference' => $result->providerReference,
            'provider_status' => $result->providerStatus,
            'message_length' => strlen(str_replace('{otp}', $code, $body)),
            'recipient_last_four' => substr(preg_replace('/\D/', '', $normalized) ?: '', -4),
            'failure_code' => $result->failureCode,
        ]);

        return $result->accepted();
    }
}
