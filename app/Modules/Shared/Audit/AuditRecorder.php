<?php

namespace App\Modules\Shared\Audit;

use App\Models\User;
use App\Modules\Shared\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditRecorder
{
    /**
     * Record a deliberately minimal audit event. Callers must not pass secrets or document contents.
     *
     * @param  array<string, scalar|null>  $metadata
     */
    public function record(?User $actor, string $action, ?Model $subject = null, array $metadata = []): AuditLog
    {
        return AuditLog::query()->create([
            'public_id' => (string) Str::ulid(),
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'subject_public_id' => $subject?->getAttribute('public_id'),
            'outcome' => 'success',
            'request_id' => request()?->header('X-Request-ID'),
            'ip_address' => request()?->ip(),
            'user_agent' => Str::limit((string) request()?->userAgent(), 1024, ''),
            'metadata' => $metadata === [] ? null : $metadata,
            'occurred_at' => now(),
        ]);
    }
}
