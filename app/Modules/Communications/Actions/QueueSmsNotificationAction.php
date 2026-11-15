<?php

namespace App\Modules\Communications\Actions;

use App\Modules\Communications\Jobs\DispatchSmsNotification;
use App\Modules\Communications\Models\Notification;
use App\Modules\Communications\Support\SmsRecipient;
use Illuminate\Database\Eloquent\Model;

/**
 * D-052: queue one SMS to one person. Who may be texted (opt-in for announcements, anyone with a
 * number for an Admin direct message) is decided by the caller; this only records and dispatches.
 * The idempotency key makes a repeated send of the same message to the same number a no-op.
 */
class QueueSmsNotificationAction
{
    public function execute(SmsRecipient $recipient, string $purpose, string $body, string $idempotencyKey, Model $subject): Notification
    {
        $notification = Notification::query()->firstOrCreate(['idempotency_key' => $idempotencyKey], [
            'recipient_guardian_id' => $recipient->type === 'guardian' ? $recipient->id : null,
            'recipient_student_id' => $recipient->type === 'student' ? $recipient->id : null,
            'recipient_user_id' => $recipient->userId,
            'purpose' => $purpose,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'status' => 'queued',
            'rendered_body' => $body,
            'scheduled_at' => now(),
        ]);
        if ($notification->wasRecentlyCreated) {
            DispatchSmsNotification::dispatch($notification->id)->afterCommit();
        }

        return $notification;
    }
}
