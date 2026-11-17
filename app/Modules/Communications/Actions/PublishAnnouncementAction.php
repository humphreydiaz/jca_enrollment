<?php

namespace App\Modules\Communications\Actions;

use App\Models\User;
use App\Modules\Communications\Models\Announcement;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** D-052: publish a saved draft; if it includes SMS, the opted-in people in its audience are texted now. */
class PublishAnnouncementAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly SendAnnouncementSmsAction $sms,
    ) {}

    public function execute(Announcement $announcement, User $actor): Announcement
    {
        $published = DB::transaction(function () use ($announcement, $actor): Announcement {
            $locked = Announcement::query()->lockForUpdate()->findOrFail($announcement->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Only draft announcements can be published.']);
            }
            $locked->update(['status' => 'published', 'published_at' => now()]);
            $this->audit->record($actor, 'communications.announcement.published', $locked, ['delivery' => $locked->delivery]);

            return $locked;
        });

        $this->sms->execute($published, $actor);

        return $published;
    }
}
