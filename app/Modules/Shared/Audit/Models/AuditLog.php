<?php

namespace App\Modules\Shared\Audit\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /** D-059: the audit trail is append-only; an entry is never edited or deleted through the application. */
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit log entries are append-only and cannot be changed.'));
        static::deleting(fn () => throw new \LogicException('Audit log entries are append-only and cannot be deleted.'));
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'actor_user_id',
        'action',
        'subject_type',
        'subject_id',
        'subject_public_id',
        'outcome',
        'request_id',
        'ip_address',
        'user_agent',
        'metadata',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
