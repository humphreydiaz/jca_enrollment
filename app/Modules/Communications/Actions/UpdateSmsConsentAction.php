<?php

namespace App\Modules\Communications\Actions;

use App\Models\User;
use App\Modules\Communications\Models\SmsConsent;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Database\Eloquent\Model;

/**
 * Record whether a person agrees to receive school announcements by SMS (D-052). The owner is the
 * person whose number is used: a parent/guardian profile, a learner record, or a staff account.
 */
class UpdateSmsConsentAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Model $owner, string $purpose, bool $granted, ?User $actor, string $source = 'portal'): SmsConsent
    {
        $column = match (true) {
            $owner instanceof GuardianProfile => 'guardian_id',
            $owner instanceof StudentProfile => 'student_id',
            $owner instanceof User => 'user_id',
        };
        $consent = SmsConsent::query()->firstOrNew([$column => $owner->getKey(), 'purpose' => $purpose]);
        // Nothing to record: already in that state, or declining something never agreed to.
        if (($consent->exists && ($consent->status === 'granted') === $granted) || (! $consent->exists && ! $granted)) {
            return $consent;
        }
        $consent->fill([
            'status' => $granted ? 'granted' : 'withdrawn',
            'source' => $source,
            'effective_at' => $granted ? now() : null,
            'withdrawn_at' => $granted ? null : now(),
            'recorded_by' => $actor?->id,
        ])->save();
        $this->audit->record($actor, 'communications.sms_consent.'.$consent->status, $consent, ['purpose' => $purpose, 'owner' => $column]);

        return $consent;
    }

    public static function granted(Model $owner, string $purpose = 'announcement'): bool
    {
        $column = match (true) {
            $owner instanceof GuardianProfile => 'guardian_id',
            $owner instanceof StudentProfile => 'student_id',
            default => 'user_id',
        };

        return SmsConsent::query()->where($column, $owner->getKey())->where('purpose', $purpose)->where('status', 'granted')->whereNull('withdrawn_at')->exists();
    }
}
