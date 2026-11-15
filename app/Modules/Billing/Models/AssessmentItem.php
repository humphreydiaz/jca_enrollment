<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AssessmentItem extends Model
{
    protected $fillable = [
        'fee_assessment_id',
        'fee_type_id',
        'fee_code',
        'fee_name',
        'category',
        'quantity',
        'unit_amount',
        'gross_amount',
        'discount_amount',
        'net_amount',
        'is_permit_critical',
    ];

    protected function casts(): array
    {
        return [
            'unit_amount' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'is_permit_critical' => 'boolean',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(
            FeeAssessment::class,
            'fee_assessment_id'
        );
    }

    protected static function booted(): void
    {
        $ensureDraft = function (self $item): void {
            $assessment = $item->assessment()->first();

            if (
                ! $assessment ||
                $assessment->status !== 'draft'
            ) {
                throw new LogicException(
                    'Only draft assessment items can be modified.'
                );
            }
        };

        static::creating($ensureDraft);
        static::updating($ensureDraft);
        static::deleting($ensureDraft);
    }
}
