<?php

namespace App\Modules\Identity\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AccountInvitation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'user_id',
        'purpose',
        'channel',
        'sent_to',
        'token_hash',
        'expires_at',
        'consumed_at',
        'revoked_at',
        'created_by',
        'revoked_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $invitation): void {
            $invitation->public_id ??= (string) Str::ulid();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }
}
