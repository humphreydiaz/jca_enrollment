<?php

namespace App\Modules\Communications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Announcement extends Model
{
    protected $fillable = ['title', 'body', 'status', 'published_at', 'publish_until', 'created_by'];

    protected static function booted(): void
    {
        static::creating(fn (self $announcement) => $announcement->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'publish_until' => 'datetime'];
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
