<?php

namespace App\Modules\Shared\Audit\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
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
}
