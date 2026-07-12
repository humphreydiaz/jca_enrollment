<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CreditAllocation extends Model
{
    protected $fillable = ['source_payment_id', 'target_assessment_item_id', 'amount', 'reason', 'allocated_by', 'allocated_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::ulid());
        static::updating(fn () => throw new \LogicException('Posted credit allocations are immutable.'));
        static::deleting(fn () => throw new \LogicException('Posted credit allocations cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'allocated_at' => 'datetime'];
    }

    public function sourcePayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'source_payment_id');
    }

    public function targetAssessmentItem(): BelongsTo
    {
        return $this->belongsTo(AssessmentItem::class, 'target_assessment_item_id');
    }
}
