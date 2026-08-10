<?php

namespace App\Modules\Communications\Actions;

use App\Models\User;
use App\Modules\Communications\Models\NotificationTemplate;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

class CreateNotificationTemplateVersionAction
{
    public function __construct(
        private readonly SafeMessageContent $content,
        private readonly AuditRecorder $audit,
    ) {}

    /** @param array{code:string, purpose:string, content:string, is_active:bool} $attributes */
    public function execute(array $attributes, User $actor): NotificationTemplate
    {
        $this->content->validate($attributes['content']);

        return DB::transaction(function () use ($attributes, $actor): NotificationTemplate {
            $versions = NotificationTemplate::query()
                ->where('code', $attributes['code'])
                ->where('channel', 'sms')
                ->lockForUpdate()
                ->get();

            if ($attributes['is_active']) {
                NotificationTemplate::query()
                    ->whereKey($versions->modelKeys())
                    ->update(['is_active' => false]);
            }

            $template = NotificationTemplate::query()->create([
                'code' => $attributes['code'],
                'channel' => 'sms',
                'purpose' => $attributes['purpose'],
                'version' => ((int) $versions->max('version')) + 1,
                'content' => $attributes['content'],
                'is_active' => $attributes['is_active'],
                'created_by' => $actor->id,
            ]);

            $this->audit->record($actor, 'communications.template.created', $template, [
                'code' => $template->code,
                'purpose' => $template->purpose,
                'version' => $template->version,
                'is_active' => $template->is_active,
            ]);

            return $template;
        });
    }
}
