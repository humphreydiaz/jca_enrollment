<?php

namespace App\Modules\Communications\Actions;

use App\Modules\Communications\Jobs\DispatchSmsNotification;
use App\Modules\Communications\Models\Notification;
use App\Modules\Communications\Models\NotificationTemplate;
use App\Modules\Communications\Models\SmsConsent;
use App\Modules\People\Models\GuardianProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class QueueSmsNotificationAction
{
    public function __construct(private readonly SafeMessageContent $content) {}

    /** @param array<string, string> $values */
    public function execute(NotificationTemplate $template, GuardianProfile $guardian, string $idempotencyKey, array $values, ?Model $subject = null): ?Notification
    {
        if (! $template->is_active || $template->channel !== 'sms' || blank($guardian->mobile)) {
            return null;
        }
        $consentPurpose = $template->purpose === 'announcement' ? 'announcement' : 'transactional';
        $consented = SmsConsent::query()->where('guardian_id', $guardian->id)->where('purpose', $consentPurpose)->where('status', 'granted')->whereNull('withdrawn_at')->exists();
        if (! $consented) {
            return null;
        }
        $body = $this->content->render($template->content, $values);

        $notification = DB::transaction(function () use ($template, $guardian, $idempotencyKey, $body, $subject): Notification {
            $record = Notification::query()->firstOrCreate(['idempotency_key' => $idempotencyKey], [
                'notification_template_id' => $template->id,
                'recipient_guardian_id' => $guardian->id,
                'recipient_user_id' => $guardian->user_id,
                'purpose' => $template->purpose,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'status' => 'queued',
                'rendered_body' => $body,
                'scheduled_at' => now(),
            ]);
            if ($record->wasRecentlyCreated) {
                DispatchSmsNotification::dispatch($record->id)->afterCommit();
            }

            return $record;
        });

        return $notification;
    }
}
