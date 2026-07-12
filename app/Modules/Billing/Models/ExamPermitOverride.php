<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ExamPermitOverride extends Model
{
    protected $fillable = ['enrollment_id', 'exam_period_id', 'reason_category', 'confidential_reason', 'supporting_document_reference', 'effective_from', 'expires_on', 'status', 'requested_by', 'verified_by', 'verified_at', 'approved_by', 'approved_at', 'decided_by', 'decided_at', 'decision_reason', 'revoked_by', 'revoked_at', 'revocation_reason'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'expires_on' => 'date', 'verified_at' => 'datetime', 'approved_at' => 'datetime', 'decided_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
