<?php

namespace App\Modules\Communications\Models;

use App\Models\User;
use App\Modules\People\Models\GuardianProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Notification extends Model
{
    protected $fillable = ['notification_template_id', 'recipient_guardian_id', 'recipient_user_id', 'purpose', 'subject_type', 'subject_id', 'status', 'idempotency_key', 'rendered_body', 'scheduled_at', 'accepted_at', 'failed_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $notification) => $notification->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['rendered_body' => 'encrypted', 'scheduled_at' => 'datetime', 'accepted_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(GuardianProfile::class, 'recipient_guardian_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function message(): HasOne
    {
        return $this->hasOne(SmsMessage::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
