<?php

namespace App\Modules\Academics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AcademicYear extends Model
{
    protected $fillable = ['code', 'name', 'starts_on', 'ends_on', 'enrollment_opens_on', 'enrollment_closes_on', 'enrollment_is_open', 'status'];

    protected static function booted(): void
    {
        static::creating(fn (self $year) => $year->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'enrollment_opens_on' => 'date', 'enrollment_closes_on' => 'date', 'enrollment_is_open' => 'boolean'];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
