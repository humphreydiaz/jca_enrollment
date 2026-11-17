<?php

namespace App\Modules\Communications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/** D-052: one Admin "message people" send, grouping the SMS queued to each picked person. */
class DirectMessage extends Model
{
    protected $fillable = ['body', 'recipient_count', 'sent_by'];

    protected static function booted(): void
    {
        static::creating(fn (self $message) => $message->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['body' => 'encrypted'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'subject');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
