<?php

namespace App\Modules\Billing\Models;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** D-067: a Registrar-confirmed sibling discount eligibility for one enrollment (history kept on withdrawal). */
class SiblingDiscountConfirmation extends Model
{
    protected $fillable = ['enrollment_id', 'active_key', 'sibling_summary', 'confirmed_by', 'confirmed_at', 'withdrawn_by', 'withdrawn_at', 'withdraw_reason'];

    protected static function booted(): void
    {
        static::creating(fn (self $confirmation) => $confirmation->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('withdrawn_at');
    }
}
