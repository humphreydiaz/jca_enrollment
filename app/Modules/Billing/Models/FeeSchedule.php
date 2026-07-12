<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FeeSchedule extends Model
{
    protected $fillable = ['academic_year_id', 'grade_level_id', 'name', 'version', 'status', 'effective_on', 'published_by', 'published_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->public_id ??= (string) Str::ulid());
        static::updating(function (self $schedule): void {
            if ($schedule->getOriginal('status') === 'published' && collect($schedule->getDirty())->except('status', 'updated_at')->isNotEmpty()) {
                throw new \LogicException('Published fee schedules are versioned, not rewritten.');
            }
        });
    }

    protected function casts(): array
    {
        return ['effective_on' => 'date', 'published_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeeScheduleItem::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
