<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Refund extends Model
{
    protected $fillable = ['fee_assessment_id', 'amount', 'reason', 'status', 'requested_by', 'approved_by', 'decided_by', 'decided_at', 'decision_reason', 'release_reference', 'released_on'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::ulid());
        static::updating(function (self $record): void {
            if (in_array($record->getOriginal('status'), ['approved', 'rejected'], true)) {
                throw new \LogicException('Decided refunds are append-only.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Refund history cannot be deleted.'));
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'decided_at' => 'datetime', 'released_on' => 'date'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
