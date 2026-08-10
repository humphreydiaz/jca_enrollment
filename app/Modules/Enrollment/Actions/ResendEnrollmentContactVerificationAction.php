<?php

namespace App\Modules\Enrollment\Actions;

use App\Modules\Enrollment\Mail\EnrollmentContactVerificationMail;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\Mail;

class ResendEnrollmentContactVerificationAction
{
    public function __construct(
        private readonly SendContactVerificationSmsAction $sms,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * Re-issue a fresh six-digit contact verification code and re-deliver it over
     * every contact channel the applicant provided. Returns the plaintext code so
     * the caller can surface it in local/testing environments.
     */
    public function execute(Enrollment $enrollment): string
    {
        $code = (string) random_int(100000, 999999);

        $enrollment->update([
            'verification_code_hash' => hash('sha256', $code),
            'verification_expires_at' => now()->addMinutes(15),
        ]);

        $guardian = $enrollment->guardian;
        if ($guardian?->email !== null) {
            Mail::to($guardian->email)->queue(new EnrollmentContactVerificationMail($enrollment->application_number, $code));
        }
        if ($guardian?->mobile !== null) {
            $this->sms->execute($enrollment, $guardian->mobile, $code);
        }

        $this->audit->record(null, 'enrollment.contact.verification.resent', $enrollment, [
            'channels' => array_values(array_filter([
                $guardian?->email !== null ? 'email' : null,
                $guardian?->mobile !== null ? 'sms' : null,
            ])),
        ]);

        return $code;
    }
}
