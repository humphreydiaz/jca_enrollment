<?php

namespace App\Modules\Communications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SmsMessage extends Model
{
    protected $fillable = ['notification_id', 'provider', 'recipient_mobile', 'recipient_hash', 'recipient_last_four', 'provider_reference', 'status', 'attempt_count', 'failure_code', 'accepted_at', 'delivered_at', 'failed_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $message) => $message->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['recipient_mobile' => 'encrypted', 'accepted_at' => 'datetime', 'delivered_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
