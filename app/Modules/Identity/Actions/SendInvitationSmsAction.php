<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Communications\Contracts\SmsGateway;
use App\Modules\Communications\Contracts\SmsGatewayResult;
use App\Modules\Communications\Enums\SmsDeliveryRoute;
use App\Modules\Identity\Models\AccountInvitation;
use App\Modules\Shared\Audit\AuditRecorder;
use Throwable;

class SendInvitationSmsAction
{
    public function __construct(
        private readonly SmsGateway $gateway,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * Deliver a one-time account activation code over Semaphore's OTP route.
     *
     * Invitations are pre-account, transactional security messages, so they are
     * sent directly through the gateway rather than the consent-gated notification
     * pipeline. The raw token is never persisted; only its hash is stored.
     */
    public function execute(AccountInvitation $invitation, string $mobile, string $activationCode): bool
    {
        $normalized = ContactNormalizer::mobile($mobile);
        if ($normalized === null) {
            return false;
        }

        $body = 'JCSGOCA account code: {otp}. Go to the enrollment website and choose Activate account. Expires in 3 days. Do not share.';

        try {
            $result = $this->gateway->send(
                $normalized,
                $body,
                'invitation:'.$invitation->id,
                SmsDeliveryRoute::Otp,
                $activationCode,
            );
        } catch (Throwable $e) {
            // A delivery/network failure must never break the connect flow — the
            // registrar still receives the setup link as a fallback.
            $result = new SmsGatewayResult('failed', null, 'gateway_exception');
        }

        $status = $result->accepted() ? 'accepted' : ($result->status === 'disabled' ? 'disabled' : 'failed');
        $this->audit->record(null, 'identity.invitation.sms.'.$status, $invitation->user, [
            'channel' => 'mobile',
            'provider' => (string) config('communications.sms.driver'),
            'route' => SmsDeliveryRoute::Otp->value,
            'provider_reference' => $result->providerReference,
            'provider_status' => $result->providerStatus,
            'message_length' => strlen(str_replace('{otp}', $activationCode, $body)),
            'recipient_last_four' => substr(preg_replace('/\D/', '', $normalized) ?: '', -4),
            'failure_code' => $result->failureCode,
        ]);

        return $result->accepted();
    }
}
