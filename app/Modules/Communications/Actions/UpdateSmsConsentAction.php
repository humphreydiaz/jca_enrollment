<?php

namespace App\Modules\Communications\Actions;

use App\Models\User;
use App\Modules\Communications\Models\SmsConsent;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\Shared\Audit\AuditRecorder;

class UpdateSmsConsentAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(GuardianProfile $guardian, string $purpose, bool $granted, User $actor): SmsConsent
    {
        $consent = SmsConsent::query()->updateOrCreate(['guardian_id' => $guardian->id, 'purpose' => $purpose], [
            'status' => $granted ? 'granted' : 'withdrawn',
            'source' => 'guardian_portal',
            'effective_at' => $granted ? now() : null,
            'withdrawn_at' => $granted ? null : now(),
            'recorded_by' => $actor->id,
        ]);
        $this->audit->record($actor, 'communications.sms_consent.'.$consent->status, $consent, ['purpose' => $purpose]);

        return $consent;
    }
}
