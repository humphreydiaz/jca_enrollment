<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountAwardEvent extends Model
{
    protected $fillable = ['discount_award_id', 'event_type', 'reason', 'actor_user_id', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(DiscountAward::class, 'discount_award_id');
    }
}
