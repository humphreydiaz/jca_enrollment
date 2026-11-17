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
     * @param  array<string, mixed>  $metadata
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

    /**
     * D-059: record an update with the names of every changed field, plus old/new values for the fields in
     * $valueFields (field => AuditValueMask type). Other personal fields are named but their values stay out of
     * the log. Records nothing and returns null when no compared field changed.
     *
     * @param  array<string, mixed>  $before  field => value before the update
     * @param  array<string, mixed>  $after  field => value after the update
     * @param  array<string, string>  $valueFields
     * @param  array<string, mixed>  $metadata
     */
    public function recordChanges(?User $actor, string $action, Model $subject, array $before, array $after, array $valueFields = [], array $metadata = []): ?AuditLog
    {
        $comparable = fn (mixed $value): mixed => match (true) {
            $value === '' => null,
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            is_bool($value) => (int) $value,
            default => $value,
        };

        $fields = [];
        $changes = [];
        foreach ($after as $field => $value) {
            $old = $comparable($before[$field] ?? null);
            $new = $comparable($value);
            if ((string) $old === (string) $new && ($old === null) === ($new === null)) {
                continue;
            }
            $fields[] = $field;
            if (isset($valueFields[$field])) {
                $changes[$field] = [
                    'old' => AuditValueMask::apply($valueFields[$field], $before[$field] ?? null),
                    'new' => AuditValueMask::apply($valueFields[$field], $value),
                ];
            }
        }

        if ($fields === []) {
            return null;
        }

        return $this->record($actor, $action, $subject, [
            ...$metadata,
            'fields' => $fields,
            ...($changes === [] ? [] : ['changes' => $changes]),
        ]);
    }
}
