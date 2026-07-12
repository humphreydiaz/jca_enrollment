<?php

namespace App\Modules\Communications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NotificationTemplate extends Model
{
    protected $fillable = ['code', 'channel', 'purpose', 'version', 'content', 'is_active', 'created_by'];

    protected static function booted(): void
    {
        static::creating(fn (self $template) => $template->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
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
