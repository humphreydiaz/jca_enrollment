<?php

namespace App\Modules\Billing\Models;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ExamPermit extends Model
{
    protected $fillable = ['permit_reference', 'enrollment_id', 'exam_period_id', 'fee_assessment_id', 'override_id', 'replaces_permit_id', 'required_amount', 'satisfied_amount', 'calculated_at', 'issued_at', 'issued_by', 'status', 'active_permit_key', 'revoked_by', 'revoked_at', 'revocation_reason'];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['required_amount' => 'decimal:2', 'satisfied_amount' => 'decimal:2', 'calculated_at' => 'datetime', 'issued_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(ExamPeriod::class, 'exam_period_id');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(FeeAssessment::class, 'fee_assessment_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function replacesPermit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_permit_id');
    }

    public function replacement(): HasOne
    {
        return $this->hasOne(self::class, 'replaces_permit_id');
    }
}
