<?php

namespace App\Modules\Communications\Actions;

use App\Models\User;
use App\Modules\Communications\Jobs\DispatchSmsNotification;
use App\Modules\Communications\Models\Notification;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

class RetrySmsNotificationAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Notification $notification, User $actor): void
    {
        if ($notification->status !== 'failed') {
            throw ValidationException::withMessages(['notification' => 'Only failed messages can be retried.']);
        }
        $notification->update(['status' => 'queued', 'failed_at' => null]);
        $this->audit->record($actor, 'communications.sms.retry_requested', $notification);
        DispatchSmsNotification::dispatch($notification->id)->afterCommit();
    }
}
