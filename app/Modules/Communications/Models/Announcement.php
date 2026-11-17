<?php

namespace App\Modules\Communications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Announcement extends Model
{
    /** D-052: how an announcement reaches people. */
    public const DELIVERIES = ['portal' => 'Portal only', 'sms' => 'SMS only', 'portal_sms' => 'Portal + SMS'];

    protected $fillable = ['title', 'body', 'delivery', 'sms_body', 'status', 'published_at', 'publish_until', 'created_by'];

    protected static function booted(): void
    {
        static::creating(fn (self $announcement) => $announcement->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'publish_until' => 'datetime'];
    }

    public function showsOnPortal(): bool
    {
        return $this->delivery !== 'sms';
    }

    public function sendsSms(): bool
    {
        return in_array($this->delivery, ['sms', 'portal_sms'], true);
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'subject');
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(AnnouncementAudience::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
