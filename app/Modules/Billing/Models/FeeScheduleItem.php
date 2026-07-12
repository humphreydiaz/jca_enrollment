<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeScheduleItem extends Model
{
    protected $fillable = ['fee_schedule_id', 'fee_type_id', 'description', 'amount', 'is_required', 'is_default_selected'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'is_required' => 'boolean', 'is_default_selected' => 'boolean'];
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    protected static function booted(): void
    {
        $denyPublishedChange = function (self $item): void {
            if ($item->schedule()->where('status', 'published')->exists()) {
                throw new \LogicException('Published fee schedule items are immutable.');
            }
        };
        static::updating($denyPublishedChange);
        static::deleting($denyPublishedChange);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(FeeSchedule::class, 'fee_schedule_id');
    }
}
