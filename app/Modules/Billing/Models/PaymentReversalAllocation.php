<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReversalAllocation extends Model
{
    protected $fillable = ['payment_reversal_id', 'payment_allocation_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function reversal(): BelongsTo
    {
        return $this->belongsTo(PaymentReversal::class, 'payment_reversal_id');
    }
}
