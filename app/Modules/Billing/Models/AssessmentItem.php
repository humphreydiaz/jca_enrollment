<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentItem extends Model
{
    protected $fillable = ['fee_assessment_id', 'fee_type_id', 'fee_code', 'fee_name', 'category', 'quantity', 'unit_amount', 'gross_amount', 'discount_amount', 'net_amount', 'is_permit_critical'];

    protected function casts(): array
    {
        return ['unit_amount' => 'decimal:2', 'gross_amount' => 'decimal:2', 'discount_amount' => 'decimal:2', 'net_amount' => 'decimal:2', 'is_permit_critical' => 'boolean'];
    }

    protected static function booted(): void
    {
        $denyWhenFrozen = function (self $item): void {
            if ($item->assessment()->whereIn('status', ['finalized', 'superseded', 'cancelled'])->exists()) {
                throw new \LogicException('Finalized assessment items are immutable.');
            }
        };
        static::updating($denyWhenFrozen);
        static::deleting($denyWhenFrozen);
    }

    public function assessment()
    {
        return $this->belongsTo(FeeAssessment::class, 'fee_assessment_id');
    }
}
