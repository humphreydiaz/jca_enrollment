<?php

namespace App\Modules\Communications\Actions;

use App\Models\User;
use App\Modules\Communications\Models\Announcement;
use App\Modules\Communications\Models\AnnouncementAudience;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

/**
 * D-052: one announcement, delivered on the portal, by SMS, or both, to Everyone or to chosen role groups.
 * Publishing with SMS texts the opted-in people in the audience right away.
 */
class CreateAnnouncementAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly SafeMessageContent $content,
        private readonly SendAnnouncementSmsAction $sms,
    ) {}

    /**
     * @param  array{title: string, body: ?string, delivery: string, sms_body: ?string, status: string}  $attributes
     * @param  list<string>|null  $roles  null means Everyone
     */
    public function execute(array $attributes, ?array $roles, User $actor): Announcement
    {
        $delivery = $attributes['delivery'];
        $smsBody = $delivery === 'portal' ? null : trim((string) $attributes['sms_body']);
        // An SMS-only announcement keeps its text as the body too, so the history and preview can show it.
        $body = $delivery === 'sms' ? (string) $smsBody : trim((string) $attributes['body']);
        $this->content->guard(['body' => $body, 'sms_body' => (string) $smsBody]);

        $announcement = DB::transaction(function () use ($attributes, $roles, $actor, $delivery, $smsBody, $body): Announcement {
            $announcement = Announcement::query()->create([
                'title' => trim($attributes['title']),
                'body' => $body,
                'delivery' => $delivery,
                'sms_body' => $smsBody,
                'status' => $attributes['status'],
                'published_at' => $attributes['status'] === 'published' ? now() : null,
                'created_by' => $actor->id,
            ]);
            if ($roles === null) {
                AnnouncementAudience::query()->create(['announcement_id' => $announcement->id, 'audience_type' => 'all']);
            } else {
                foreach (array_values(array_intersect(array_keys(AnnouncementAudience::ROLES), $roles)) as $role) {
                    AnnouncementAudience::query()->create(['announcement_id' => $announcement->id, 'audience_type' => 'role', 'role_code' => $role]);
                }
            }
            $this->audit->record($actor, 'communications.announcement.created', $announcement, ['status' => $announcement->status, 'delivery' => $delivery]);

            return $announcement;
        });

        $this->sms->execute($announcement, $actor);

        return $announcement;
    }
}
