<?php

namespace App\Modules\Communications\Jobs;

use App\Modules\Communications\Contracts\SmsGateway;
use App\Modules\Communications\Models\Notification;
use App\Modules\Communications\Models\SmsMessage;
use App\Modules\Communications\Support\SmsLength;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class DispatchSmsNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public int $notificationId) {}

    public function handle(SmsGateway $gateway, AuditRecorder $audit): void
    {
        DB::transaction(function () use ($gateway, $audit): void {
            $notification = Notification::query()->lockForUpdate()->with(['guardian', 'student', 'recipient'])->findOrFail($this->notificationId);
            if (in_array($notification->status, ['accepted', 'logged', 'disabled'], true)) {
                return;
            }
            $mobile = (string) $notification->recipientMobile();
            if ($mobile === '') {
                $notification->update(['status' => 'failed', 'failed_at' => now()]);

                return;
            }
            $message = SmsMessage::query()->firstOrCreate(['notification_id' => $notification->id], [
                'provider' => (string) config('communications.sms.driver'),
                'recipient_mobile' => $mobile,
                'recipient_hash' => hash('sha256', $mobile),
                'recipient_last_four' => substr(preg_replace('/\D/', '', $mobile) ?: '', -4),
                'status' => 'queued',
            ]);
            $message->increment('attempt_count');
            $result = $gateway->send($mobile, (string) $notification->rendered_body, $notification->idempotency_key, SmsLength::route());
            if ($result->accepted()) {
                $status = $result->status;
                $notification->update(['status' => $status, 'accepted_at' => now(), 'failed_at' => null]);
                $message->update(['status' => $status, 'provider_reference' => $result->providerReference, 'accepted_at' => now(), 'failure_code' => null, 'failed_at' => null]);
                $audit->record(null, 'communications.sms.dispatched', $notification, ['provider' => $message->provider, 'status' => $status, 'route' => SmsLength::route()->value, 'parts' => SmsLength::parts((string) $notification->rendered_body)]);

                return;
            }
            $status = $result->status === 'disabled' ? 'disabled' : 'failed';
            $notification->update(['status' => $status, 'failed_at' => $status === 'failed' ? now() : null]);
            $message->update(['status' => $status, 'failure_code' => $result->failureCode, 'failed_at' => $status === 'failed' ? now() : null]);
            $audit->record(null, 'communications.sms.'.$status, $notification, ['provider' => $message->provider, 'failure_code' => $result->failureCode]);
        });
    }
}
