<?php

namespace App\Modules\Billing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Payment extends Model
{
    protected $fillable = ['payment_reference', 'receipt_reference', 'fee_assessment_id', 'received_on', 'amount', 'method', 'payer_name', 'method_reference', 'bank_name', 'method_description', 'status', 'created_by', 'posted_by', 'posted_at', 'cancellation_reason', 'cancelled_by', 'cancelled_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->public_id ??= (string) Str::ulid());
        static::updating(function (self $payment): void {
            if ($payment->getOriginal('status') === 'posted') {
                throw new \LogicException('Posted payments are immutable.');
            }
        });
        static::deleting(function (self $payment): void {
            if ($payment->status === 'posted') {
                throw new \LogicException('Posted payments cannot be deleted.');
            }
        });
    }

    protected function casts(): array
    {
        return ['received_on' => 'date', 'amount' => 'decimal:2', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(FeeAssessment::class, 'fee_assessment_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(PaymentReversal::class);
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
