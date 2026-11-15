<?php

namespace App\Modules\Academics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Curriculum extends Model
{
    protected $fillable = ['academic_year_id', 'version', 'name', 'status', 'source_note', 'published_by', 'published_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $curriculum) => $curriculum->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function gradeSubjects(): HasMany
    {
        return $this->hasMany(CurriculumGradeSubject::class);
    }

    public function sectionTemplates(): HasMany
    {
        return $this->hasMany(CurriculumSectionTemplate::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
