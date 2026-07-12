<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DiscountAward extends Model
{
    protected $fillable = ['discount_campaign_id', 'enrollment_id', 'fee_assessment_id', 'rank', 'amount', 'status', 'awarded_at', 'awarded_by', 'cancelled_at', 'cancelled_by', 'cancellation_reason'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::ulid());
        static::updating(function (self $award): void {
            if ($award->getOriginal('status') === 'cancelled') {
                throw new \LogicException('Cancelled discount awards are immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Discount award history cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'awarded_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(DiscountCampaign::class, 'discount_campaign_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DiscountAwardEvent::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
