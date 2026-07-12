<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAllocation extends Model
{
    protected $fillable = ['payment_id', 'assessment_item_id', 'payment_schedule_item_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        $denyPosted = function (self $allocation): void {
            if ($allocation->payment()->where('status', 'posted')->exists()) {
                throw new \LogicException('Posted payment allocations are immutable.');
            }
        };
        static::updating($denyPosted);
        static::deleting($denyPosted);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
