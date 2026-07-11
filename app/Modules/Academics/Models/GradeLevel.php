<?php

namespace App\Modules\Academics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GradeLevel extends Model
{
    protected $fillable = ['code', 'name', 'sequence', 'school_stage', 'next_grade_level_id', 'is_active'];

    protected static function booted(): void
    {
        static::creating(fn (self $gradeLevel) => $gradeLevel->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function nextGradeLevel(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_grade_level_id');
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
