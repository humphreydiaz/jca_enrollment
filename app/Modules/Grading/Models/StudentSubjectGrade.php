<?php

namespace App\Modules\Grading\Models;

use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StudentSubjectGrade extends Model
{
    protected $fillable = [
        'enrollment_id', 'subject_id', 'section_subject_id', 'teaching_assignment_id',
        'subject_code_snapshot', 'subject_name_snapshot', 'quarter_1', 'quarter_2',
        'quarter_3', 'quarter_4', 'final_grade', 'status', 'submitted_by',
        'submitted_at', 'finalized_by', 'finalized_at',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $grade) => $grade->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return [
            'quarter_1' => 'integer', 'quarter_2' => 'integer', 'quarter_3' => 'integer',
            'quarter_4' => 'integer', 'final_grade' => 'integer',
            'submitted_at' => 'datetime', 'finalized_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function sectionSubject(): BelongsTo
    {
        return $this->belongsTo(SectionSubject::class);
    }

    public function teachingAssignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class);
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(GradeCorrectionRequest::class, 'student_subject_grade_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(GradeRevision::class, 'student_subject_grade_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function isComplete(): bool
    {
        return $this->quarter_1 !== null && $this->quarter_2 !== null
            && $this->quarter_3 !== null && $this->quarter_4 !== null;
    }
}
