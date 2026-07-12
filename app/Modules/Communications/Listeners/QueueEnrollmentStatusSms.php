<?php

namespace App\Modules\Communications\Listeners;

use App\Modules\Communications\Actions\QueueSmsNotificationAction;
use App\Modules\Communications\Models\NotificationTemplate;
use App\Modules\Enrollment\Events\EnrollmentStatusChanged;

class QueueEnrollmentStatusSms
{
    public function __construct(private readonly QueueSmsNotificationAction $queue) {}

    public function handle(EnrollmentStatusChanged $event): void
    {
        if (! in_array($event->to, ['approved', 'rejected', 'waitlisted'], true)) {
            return;
        }
        $template = NotificationTemplate::query()->where('code', 'enrollment_status')->where('channel', 'sms')->where('is_active', true)->latest('version')->first();
        $guardian = $event->enrollment->guardian;
        if ($template === null || $guardian === null) {
            return;
        }
        $this->queue->execute($template, $guardian, 'enrollment-status:'.$event->enrollment->id.':'.$event->to.':'.$guardian->id, [
            'school_name' => (string) config('app.name'),
            'status' => str_replace('_', ' ', $event->to),
            'action_url' => route('guardian.enrollment.progress'),
            'announcement_title' => '',
            'announcement_body' => '',
        ], $event->enrollment);
    }
}
