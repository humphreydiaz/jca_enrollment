<?php

namespace App\Modules\Enrollment\Models;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Billing\Models\DiscountAward;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\SectionAssignment\Models\PromotionRecommendation;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Enrollment extends Model
{
    protected $fillable = ['application_number', 'student_id', 'academic_year_id', 'grade_level_id', 'applicant_guardian_id', 'student_category', 'guardian_relationship', 'previous_school_name', 'status', 'active_enrollment_key', 'resume_token_hash', 'verification_code_hash', 'verification_expires_at', 'verification_attempts', 'duplicate_review_status', 'potential_duplicate_student_id', 'potential_duplicate_guardian_id', 'duplicate_reviewed_by', 'duplicate_reviewed_at', 'duplicate_review_reason', 'contact_verified_at', 'submitted_at', 'approved_at', 'enrolled_at', 'rejected_at', 'withdrawn_at', 'decided_by', 'decision_reason', 'previous_enrollment_id', 'lock_version'];

    protected static function booted(): void
    {
        static::creating(fn (self $record) => $record->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['verification_expires_at' => 'datetime', 'duplicate_reviewed_at' => 'datetime', 'contact_verified_at' => 'datetime', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'enrolled_at' => 'datetime', 'rejected_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(GuardianProfile::class, 'applicant_guardian_id');
    }

    public function potentialDuplicateStudent(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'potential_duplicate_student_id');
    }

    public function potentialDuplicateGuardian(): BelongsTo
    {
        return $this->belongsTo(GuardianProfile::class, 'potential_duplicate_guardian_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(EnrollmentRequirement::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(EnrollmentStatusHistory::class);
    }

    public function sectionAssignments(): HasMany
    {
        return $this->hasMany(SectionAssignment::class);
    }

    public function currentSectionAssignment(): HasOne
    {
        return $this->hasOne(SectionAssignment::class)->where('status', 'confirmed')->whereNull('ended_at')->latestOfMany();
    }

    public function previousEnrollment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_enrollment_id');
    }

    public function promotionRecommendation(): HasOne
    {
        return $this->hasOne(PromotionRecommendation::class, 'source_enrollment_id');
    }

    public function discountAwards(): HasMany
    {
        return $this->hasMany(DiscountAward::class);
    }

    public function feeAssessments(): HasMany
    {
        return $this->hasMany(FeeAssessment::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
