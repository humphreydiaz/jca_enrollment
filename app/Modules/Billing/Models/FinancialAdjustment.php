<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FinancialAdjustment extends Model
{
    protected $fillable = ['fee_assessment_id', 'assessment_item_id', 'adjustment_type', 'amount', 'reason', 'status', 'requested_by', 'decided_by', 'decided_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->public_id ??= (string) Str::ulid());
        static::updating(function (self $record): void {
            if (in_array($record->getOriginal('status'), ['approved', 'rejected'], true)) {
                throw new \LogicException('Decided adjustments are append-only.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Adjustment history cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'decided_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function assessmentItem(): BelongsTo
    {
        return $this->belongsTo(AssessmentItem::class);
    }
}
