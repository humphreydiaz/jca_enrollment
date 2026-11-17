<?php

namespace App\Modules\Academics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GradeLevel extends Model
{
    /** @var list<string> */
    private const STUDENT_SELF_APPLICATION_STAGES = ['Junior High School'];

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

    public function allowsStudentAccountByDefault(): bool
    {
        return in_array(
            Str::lower(trim((string) $this->school_stage)),
            array_map(Str::lower(...), self::STUDENT_SELF_APPLICATION_STAGES),
            true,
        );
    }

    public function scopeAvailableForApplicant(Builder $query, bool $studentSelfApplicant): Builder
    {
        if ($studentSelfApplicant) {
            return $query->whereIn('school_stage', self::STUDENT_SELF_APPLICATION_STAGES);
        }

        return $query->where(fn (Builder $stageQuery) => $stageQuery
            ->whereNull('school_stage')
            ->orWhereNotIn('school_stage', self::STUDENT_SELF_APPLICATION_STAGES));
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
