<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Communications\Contracts\SmsGateway;
use App\Modules\Communications\Contracts\SmsGatewayResult;
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
     * Deliver a one-time account-setup link over SMS.
     *
     * Invitations are pre-account, transactional security messages, so they are
     * sent directly through the gateway rather than the consent-gated notification
     * pipeline. The raw token is never persisted beyond the invitation itself.
     */
    public function execute(AccountInvitation $invitation, string $mobile, string $setupUrl): bool
    {
        $normalized = ContactNormalizer::mobile($mobile);
        if ($normalized === null) {
            return false;
        }

        $school = trim((string) config('app.name')) ?: 'Enrollment';
        $body = "{$school}: activate your enrollment portal account. Open {$setupUrl} within 3 days. Do not share this link.";

        try {
            $result = $this->gateway->send($normalized, $body, 'invitation:'.$invitation->id);
        } catch (Throwable $e) {
            // A delivery/network failure must never break the connect flow — the
            // registrar still receives the setup link as a fallback.
            $result = new SmsGatewayResult('failed', null, 'gateway_exception');
        }

        $status = $result->accepted() ? 'sent' : ($result->status === 'disabled' ? 'disabled' : 'failed');
        $this->audit->record(null, 'identity.invitation.sms.'.$status, $invitation->user, [
            'channel' => 'mobile',
            'provider' => (string) config('communications.sms.driver'),
            'recipient_last_four' => substr(preg_replace('/\D/', '', $normalized) ?: '', -4),
            'failure_code' => $result->failureCode,
        ]);

        return $result->accepted();
    }
}
