<?php

namespace App\Modules\Billing\Models;

use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class FeeAssessment extends Model
{
    protected $fillable = ['assessment_reference', 'enrollment_id', 'fee_schedule_id', 'previous_assessment_id', 'revision_number', 'status', 'currency', 'gross_total', 'discount_total', 'adjustment_total', 'net_total', 'revision_reason', 'created_by', 'finalized_by', 'finalized_at', 'current_finalized_key'];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->public_id ??= (string) Str::ulid());
        static::updating(function (self $assessment): void {
            if (in_array($assessment->getOriginal('status'), ['finalized', 'superseded', 'cancelled'], true)) {
                throw new \LogicException('Finalized assessment snapshots are immutable.');
            }
        });
        static::deleting(function (self $assessment): void {
            if ($assessment->status !== 'draft') {
                throw new \LogicException('Historical assessments cannot be deleted.');
            }
        });
    }

    protected function casts(): array
    {
        return ['gross_total' => 'decimal:2', 'discount_total' => 'decimal:2', 'adjustment_total' => 'decimal:2', 'net_total' => 'decimal:2', 'finalized_at' => 'datetime'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssessmentItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function plan(): HasOne
    {
        return $this->hasOne(PaymentPlan::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(FinancialAdjustment::class);
    }

    public function discountAwards(): HasMany
    {
        return $this->hasMany(DiscountAward::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
