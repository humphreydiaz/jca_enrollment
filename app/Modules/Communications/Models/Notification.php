<?php

namespace App\Modules\Communications\Models;

use App\Models\User;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Notification extends Model
{
    protected $fillable = ['notification_template_id', 'recipient_guardian_id', 'recipient_user_id', 'recipient_student_id', 'purpose', 'subject_type', 'subject_id', 'status', 'idempotency_key', 'rendered_body', 'scheduled_at', 'accepted_at', 'failed_at'];

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

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'recipient_student_id');
    }

    /** The number this message goes to: the parent's, else the learner's, else the account's (staff). */
    public function recipientMobile(): ?string
    {
        if ($this->recipient_guardian_id !== null) {
            return $this->guardian?->mobile;
        }
        if ($this->recipient_student_id !== null) {
            return $this->student?->mobile;
        }

        return $this->recipient?->mobile;
    }

    public function recipientName(): string
    {
        return (string) ($this->guardian?->full_name ?? $this->student?->full_name ?? $this->recipient?->name ?? 'Unknown recipient');
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
