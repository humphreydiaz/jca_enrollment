<?php

namespace App\Modules\SectionAssignment\Models;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PromotionRecommendation extends Model
{
    protected $fillable = ['source_enrollment_id', 'target_academic_year_id', 'target_grade_level_id', 'eligibility_status', 'reason', 'generated_by', 'generated_at', 'created_enrollment_id', 'confirmed_by', 'confirmed_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $recommendation) => $recommendation->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['generated_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }

    public function sourceEnrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'source_enrollment_id');
    }

    public function targetAcademicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'target_academic_year_id');
    }

    public function targetGradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'target_grade_level_id');
    }

    public function createdEnrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'created_enrollment_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
