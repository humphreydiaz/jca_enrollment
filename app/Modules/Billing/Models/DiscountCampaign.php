<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DiscountCampaign extends Model
{
    protected $fillable = ['academic_year_id', 'code', 'name', 'value_type', 'value_amount', 'slot_limit', 'starts_on', 'ends_on', 'is_active', 'is_development_fixture'];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['value_amount' => 'decimal:2', 'starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean', 'is_development_fixture' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
