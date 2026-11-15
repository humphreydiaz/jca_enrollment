<?php

namespace App\Modules\Communications\Actions;

use App\Models\User;
use App\Modules\Communications\Models\DirectMessage;
use App\Modules\Communications\Queries\SmsRecipientDirectory;
use App\Modules\Communications\Support\SmsLength;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * D-052: the Admin texts one or more people they picked by name. Because each person was chosen
 * deliberately, the message is sent whether or not they opted in to announcement SMS. One text
 * per number; the body is stored encrypted and never written to the audit log.
 */
class SendDirectMessageAction
{
    public function __construct(
        private readonly SmsRecipientDirectory $directory,
        private readonly QueueSmsNotificationAction $queue,
        private readonly AuditRecorder $audit,
    ) {}

    /** @param  list<string>  $recipientKeys */
    public function execute(array $recipientKeys, string $body, User $actor): DirectMessage
    {
        $body = trim($body);
        $recipients = $this->directory->resolve($recipientKeys)->unique(fn ($recipient) => $recipient->mobileHash())->values();
        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages(['recipients' => 'Choose at least one person with a mobile number on file.']);
        }

        return DB::transaction(function () use ($recipients, $body, $actor): DirectMessage {
            $message = DirectMessage::query()->create(['body' => $body, 'recipient_count' => $recipients->count(), 'sent_by' => $actor->id]);
            foreach ($recipients as $recipient) {
                $this->queue->execute($recipient, 'direct', $body, 'direct:'.$message->id.':'.$recipient->mobileHash(), $message);
            }
            $this->audit->record($actor, 'communications.direct_message.sent', $message, [
                'recipients' => $recipients->count(),
                'parts' => SmsLength::parts($body),
                'route' => SmsLength::route()->value,
            ]);

            return $message;
        });
    }
}
