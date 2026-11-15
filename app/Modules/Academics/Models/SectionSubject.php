<?php

namespace App\Modules\Academics\Models;

use App\Modules\Academics\Support\ClassSchedule;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SectionSubject extends Model
{
    protected $fillable = ['section_id', 'subject_id', 'curriculum_id', 'status', 'days', 'starts_at', 'ends_at', 'schedule_text'];

    protected $attributes = ['status' => 'active'];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Eager-load for the portal schedules and the section print: class-time order, the subject, and the
     * assigned (not ended) subject teacher. Past school years keep showing who taught the class.
     */
    public static function scheduleWithTeachers(): Closure
    {
        return fn ($query) => $query->orderByRaw('starts_at IS NULL')->orderBy('starts_at')->orderBy('id')->with([
            'subject',
            'teachingAssignments' => fn ($teachers) => $teachers->where('is_active', true)->where('assignment_type', 'subject_teacher')->with('staffProfile.user'),
        ]);
    }

    /** Names of the subject teacher(s) loaded by scheduleWithTeachers(); empty when none is assigned. */
    public function teacherNames(): string
    {
        return $this->teachingAssignments->map(fn ($assignment) => $assignment->staffProfile?->user?->name)->filter()->unique()->join(', ');
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function classSchedule(): ?ClassSchedule
    {
        return ClassSchedule::fromRecord($this);
    }
}
