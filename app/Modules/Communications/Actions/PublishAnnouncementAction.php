<?php

namespace App\Modules\Communications\Actions;

use App\Models\User;
use App\Modules\Communications\Models\Announcement;
use App\Modules\Communications\Models\NotificationTemplate;
use App\Modules\Communications\Queries\AnnouncementSmsRecipients;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishAnnouncementAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly QueueSmsNotificationAction $sms,
        private readonly AnnouncementSmsRecipients $recipients,
    ) {}

    /**
     * Publish an existing draft announcement. When an active announcement SMS
     * template is supplied, the message is also queued to the consented
     * guardians in the announcement's stored audience — mirroring the
     * publish-with-SMS behaviour of creating a new published announcement.
     */
    public function execute(Announcement $announcement, ?string $smsTemplatePublicId, User $actor): Announcement
    {
        if ($announcement->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only draft announcements can be published.']);
        }

        $template = blank($smsTemplatePublicId) ? null : NotificationTemplate::query()
            ->where('public_id', $smsTemplatePublicId)->where('channel', 'sms')->where('purpose', 'announcement')->where('is_active', true)->first();
        if (! blank($smsTemplatePublicId) && $template === null) {
            throw ValidationException::withMessages(['sms_template_public_id' => 'Choose an active announcement SMS template.']);
        }

        return DB::transaction(function () use ($announcement, $template, $actor): Announcement {
            $announcement->update([
                'status' => 'published',
                'published_at' => $announcement->published_at ?? now(),
            ]);
            $this->audit->record($actor, 'communications.announcement.published', $announcement, ['sms' => $template !== null]);

            if ($template !== null) {
                $audiences = $announcement->audiences->map(fn ($audience) => [
                    'type' => $audience->audience_type,
                    'role_code' => $audience->role_code,
                    'section_id' => $audience->section_id,
                    'guardian_id' => $audience->guardian_id,
                ])->all();

                foreach ($this->recipients->forAudiences($audiences) as $guardian) {
                    $this->sms->execute($template, $guardian, 'announcement:'.$announcement->id.':'.$guardian->id, [
                        'school_name' => (string) config('app.name'),
                        'status' => '',
                        'action_url' => route('communications.show', $announcement),
                        'announcement_title' => $announcement->title,
                        'announcement_body' => $announcement->body,
                    ], $announcement);
                }
            }

            return $announcement;
        });
    }
}
