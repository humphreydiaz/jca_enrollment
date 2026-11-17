<?php

namespace App\Modules\Academics\Models;

use App\Modules\Academics\Support\ActivityProgram;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Section extends Model
{
    protected $fillable = ['academic_year_id', 'grade_level_id', 'curriculum_id', 'name', 'activity_schedule', 'capacity', 'sort_order', 'status'];

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

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    /** Current subject offerings. Archived offerings are kept for history; see allSectionSubjects(). */
    public function sectionSubjects(): HasMany
    {
        return $this->hasMany(SectionSubject::class)->where('status', 'active');
    }

    /** Every offering, including archived ones, for Academics administration. */
    public function allSectionSubjects(): HasMany
    {
        return $this->hasMany(SectionSubject::class);
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function sectionAssignments(): HasMany
    {
        return $this->hasMany(SectionAssignment::class);
    }

    /**
     * The activity schedule one item per line (see ActivityProgram::lines()).
     *
     * @return list<string>
     */
    public function activityLines(): array
    {
        return ActivityProgram::lines($this->activity_schedule);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
