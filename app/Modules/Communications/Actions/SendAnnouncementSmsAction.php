<?php

namespace App\Modules\Communications\Actions;

use App\Models\User;
use App\Modules\Communications\Models\Announcement;
use App\Modules\Communications\Models\AnnouncementAudience;
use App\Modules\Communications\Queries\SmsRecipientDirectory;
use App\Modules\Communications\Support\SmsLength;
use App\Modules\Shared\Audit\AuditRecorder;

/**
 * D-052: text a published announcement to everyone in its audience who has a number on file and
 * opted in. One text per number; sending the same announcement again never texts anyone twice.
 */
class SendAnnouncementSmsAction
{
    public function __construct(
        private readonly SmsRecipientDirectory $directory,
        private readonly QueueSmsNotificationAction $queue,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(Announcement $announcement, User $actor): int
    {
        if ($announcement->status !== 'published' || ! $announcement->sendsSms() || blank($announcement->sms_body)) {
            return 0;
        }

        $audience = $this->directory->forAudience($this->roles($announcement));
        foreach ($audience['recipients'] as $recipient) {
            $this->queue->execute($recipient, 'announcement', (string) $announcement->sms_body, 'announcement:'.$announcement->id.':'.$recipient->mobileHash(), $announcement);
        }

        $count = $audience['recipients']->count();
        $this->audit->record($actor, 'communications.announcement.sms_queued', $announcement, [
            'recipients' => $count,
            'parts' => SmsLength::parts((string) $announcement->sms_body),
            'route' => SmsLength::route()->value,
        ]);

        return $count;
    }

    /** @return list<string> */
    public function roles(Announcement $announcement): array
    {
        $announcement->loadMissing('audiences');
        if ($announcement->audiences->contains('audience_type', 'all')) {
            return array_keys(AnnouncementAudience::ROLES);
        }

        return $announcement->audiences->where('audience_type', 'role')->pluck('role_code')->filter()->values()->all();
    }
}
