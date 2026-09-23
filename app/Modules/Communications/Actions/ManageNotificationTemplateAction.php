<?php

namespace App\Modules\Communications\Actions;

use App\Models\User;
use App\Modules\Communications\Models\Notification;
use App\Modules\Communications\Models\NotificationTemplate;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageNotificationTemplateAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * Activate or deactivate a template version. Activating a version deactivates
     * any other version sharing its code + channel, so at most one is ever active
     * — the same rule the creation flow enforces.
     */
    public function setActive(NotificationTemplate $template, bool $isActive, User $actor): NotificationTemplate
    {
        return DB::transaction(function () use ($template, $isActive, $actor): NotificationTemplate {
            $locked = NotificationTemplate::query()->lockForUpdate()->findOrFail($template->id);

            if ($isActive) {
                NotificationTemplate::query()
                    ->where('code', $locked->code)
                    ->where('channel', $locked->channel)
                    ->whereKeyNot($locked->id)
                    ->update(['is_active' => false]);
            }

            $locked->update(['is_active' => $isActive]);
            $this->audit->record(
                $actor,
                $isActive ? 'communications.template.activated' : 'communications.template.deactivated',
                $locked,
                ['code' => $locked->code, 'version' => $locked->version],
            );

            return $locked;
        });
    }

    /**
     * Delete a template version — only when it has never been used to send a
     * notification. A used version is kept for audit history; deactivate it
     * instead so it stops being selected while the record is preserved.
     */
    public function delete(NotificationTemplate $template, User $actor): void
    {
        DB::transaction(function () use ($template, $actor): void {
            $locked = NotificationTemplate::query()->lockForUpdate()->findOrFail($template->id);

            $usage = $this->usageCount($locked);
            if ($usage > 0) {
                throw ValidationException::withMessages([
                    'template' => "This version was already used for {$usage} message(s). Deactivate it instead so history is preserved.",
                ]);
            }

            $this->audit->record($actor, 'communications.template.deleted', $locked, ['code' => $locked->code, 'version' => $locked->version]);
            $locked->delete();
        });
    }

    public function usageCount(NotificationTemplate $template): int
    {
        return Notification::query()->where('notification_template_id', $template->id)->count();
    }
}
