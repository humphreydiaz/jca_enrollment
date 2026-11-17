<?php

namespace App\Modules\Communications\Queries;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * D-070: what the Admin needs to see to know texting works, without ever showing the API key:
 * whether live SMS is on, which sender is used, the Semaphore credit balance, and whether the
 * queue that sends texts is keeping up (texts are sent by the queue worker, which on Hostinger
 * runs from an hPanel cron job).
 */
class SmsStatusQuery
{
    /** @return array<string, mixed> */
    public function status(): array
    {
        $enabled = (bool) config('communications.sms.enabled');
        $driver = (string) config('communications.sms.driver');
        $keySet = filled(config('communications.sms.semaphore.api_key'));
        $sender = trim((string) config('communications.sms.semaphore.sender_name'));
        $mode = match (true) {
            $enabled && $driver === 'semaphore' && $keySet => 'live',
            $enabled && $driver === 'semaphore' => 'missing_key',
            $enabled && $driver === 'log' => 'test',
            default => 'off',
        };

        $pending = DB::table('jobs')->count();
        $oldestPending = DB::table('jobs')->min('created_at');
        $lastSent = DB::table('sms_messages')->whereNotNull('accepted_at')->max('accepted_at');
        $lastFailure = DB::table('sms_messages')->where('status', 'failed')->latest('updated_at')->value('failure_code');

        return [
            'mode' => $mode,
            'driver' => $driver,
            'key_set' => $keySet,
            'sender' => $sender !== '' ? $sender : null,
            'route' => (string) config('communications.sms.message_route', 'priority'),
            'balance' => $mode === 'live' ? $this->balance() : null,
            'pending_jobs' => $pending,
            // A job waiting more than 10 minutes means the queue worker (cron) is not running.
            'queue_stalled' => $oldestPending !== null && (int) $oldestPending < now()->subMinutes(10)->getTimestamp(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'last_sent_at' => $lastSent,
            'last_failure' => $lastFailure,
        ];
    }

    /** Semaphore credit balance, cached for five minutes; null when it cannot be read. */
    private function balance(): ?string
    {
        return Cache::remember('communications.semaphore.balance', 300, function (): ?string {
            try {
                $response = Http::connectTimeout(3)->timeout(6)
                    ->get(rtrim((string) config('communications.sms.semaphore.base_url'), '/').'/api/v4/account', [
                        'apikey' => (string) config('communications.sms.semaphore.api_key'),
                    ]);
                $balance = $response->successful() ? data_get($response->json(), 'credit_balance') : null;

                return is_numeric($balance) ? number_format((float) $balance, 2) : null;
            } catch (Throwable) {
                return null; // Never surface provider errors or the key; the panel shows "could not be read".
            }
        });
    }
}
