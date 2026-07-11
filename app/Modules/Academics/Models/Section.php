<?php

namespace App\Modules\Academics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Section extends Model
{
    protected $fillable = ['academic_year_id', 'grade_level_id', 'name', 'capacity', 'sort_order', 'status'];

    protected static function booted(): void
    {
        static::creating(fn (self $section) => $section->public_id ??= (string) Str::ulid());
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function sectionSubjects(): HasMany
    {
        return $this->hasMany(SectionSubject::class);
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
