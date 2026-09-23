<?php

namespace App\Modules\Communications\Actions;

use App\Models\User;
use App\Modules\Communications\Models\Announcement;
use App\Modules\Communications\Models\AnnouncementAudience;
use App\Modules\Communications\Models\NotificationTemplate;
use App\Modules\Communications\Queries\AnnouncementSmsRecipients;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAnnouncementAction
{
    public function __construct(private readonly AuditRecorder $audit, private readonly SafeMessageContent $content, private readonly QueueSmsNotificationAction $sms, private readonly AnnouncementSmsRecipients $recipients) {}

    /** @param list<array<string, mixed>> $audiences */
    public function execute(array $attributes, array $audiences, User $actor): Announcement
    {
        $this->content->validate((string) $attributes['body']);
        if ($audiences === []) {
            throw ValidationException::withMessages(['audiences' => 'At least one audience is required.']);
        }

        $template = empty($attributes['sms_template_public_id']) ? null : NotificationTemplate::query()
            ->where('public_id', $attributes['sms_template_public_id'])->where('channel', 'sms')->where('purpose', 'announcement')->where('is_active', true)->first();
        if (! empty($attributes['sms_template_public_id']) && $template === null) {
            throw ValidationException::withMessages(['sms_template_public_id' => 'Choose an active announcement SMS template.']);
        }

        return DB::transaction(function () use ($attributes, $audiences, $actor, $template): Announcement {
            $announcement = Announcement::query()->create([
                'title' => $attributes['title'],
                'body' => $attributes['body'],
                'status' => $attributes['status'],
                'published_at' => $attributes['status'] === 'published' ? ($attributes['published_at'] ?? now()) : null,
                'publish_until' => $attributes['publish_until'] ?? null,
                'created_by' => $actor->id,
            ]);
            foreach ($audiences as $audience) {
                $type = $audience['type'] ?? null;
                if (! in_array($type, ['all', 'role', 'section', 'student', 'guardian'], true)) {
                    throw ValidationException::withMessages(['audiences' => 'An audience type is invalid.']);
                }
                $values = ['announcement_id' => $announcement->id, 'audience_type' => $type];
                if ($type === 'role' && ! empty($audience['role_code'])) {
                    $values['role_code'] = $audience['role_code'];
                } elseif ($type === 'section' && ! empty($audience['section_id'])) {
                    $values['section_id'] = $audience['section_id'];
                } elseif ($type === 'student' && ! empty($audience['student_id'])) {
                    $values['student_id'] = $audience['student_id'];
                } elseif ($type === 'guardian' && ! empty($audience['guardian_id'])) {
                    $values['guardian_id'] = $audience['guardian_id'];
                } elseif ($type !== 'all') {
                    throw ValidationException::withMessages(['audiences' => 'Each targeted audience needs a valid target.']);
                }
                AnnouncementAudience::query()->create($values);
            }
            $this->audit->record($actor, 'communications.announcement.created', $announcement, ['status' => $announcement->status]);
            if ($template !== null && $announcement->status === 'published') {
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
