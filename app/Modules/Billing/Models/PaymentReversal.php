<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentReversal extends Model
{
    protected $fillable = ['payment_id', 'amount', 'reason', 'status', 'requested_by', 'requested_at', 'decided_by', 'decided_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->public_id ??= (string) Str::ulid());
        static::updating(function (self $record): void {
            if (in_array($record->getOriginal('status'), ['approved', 'rejected'], true)) {
                throw new \LogicException('Decided reversals are append-only.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Reversal history cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'requested_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
