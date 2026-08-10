<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentPlan extends Model
{
    protected $fillable = ['fee_assessment_id', 'plan_type', 'status', 'selected_by', 'selected_at'];

    protected function casts(): array
    {
        return ['selected_at' => 'datetime'];
    }

    public function feeAssessment(): BelongsTo
    {
        return $this->belongsTo(FeeAssessment::class);
    }

    public function scheduleItems(): HasMany
    {
        return $this->hasMany(PaymentScheduleItem::class);
    }
}
