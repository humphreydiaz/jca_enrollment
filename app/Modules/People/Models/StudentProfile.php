<?php

namespace App\Modules\People\Models;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class StudentProfile extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'user_id',
        'student_number',
        'lrn',
        'first_name',
        'last_name',
        'middle_name',
        'extension_name',
        'preferred_name',
        'sex',
        'birth_date',
        'birth_place',
        'mother_tongue',
        'religion',
        'ip_group',
        'is_4ps_recipient',
        'four_ps_household_id',
        'is_learner_with_disability',
        'disability_type',
        'current_address',
        'permanent_address',
        'mobile',
        'father_first_name',
        'father_middle_name',
        'father_last_name',
        'father_extension_name',
        'mother_first_name',
        'mother_middle_name',
        'mother_maiden_last_name',
        'mother_extension_name',
        'status',
        'archived_at',
        'graduated_at',
        'transferred_out_at',
        'archive_reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $student): void {
            $student->public_id ??= (string) Str::ulid();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'archived_at' => 'datetime',
            'graduated_at' => 'datetime',
            'transferred_out_at' => 'datetime',
            'dropped_at' => 'date',
            'is_4ps_recipient' => 'boolean',
            'is_learner_with_disability' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<GuardianProfile, $this>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(GuardianProfile::class, 'guardian_student', 'student_id', 'guardian_id')
            ->withPivot([
                'id',
                'relationship_type',
                'is_primary_contact',
                'may_receive_sms',
                'may_view_financials',
                'may_manage_enrollment',
                'has_legal_authority',
                'verified_at',
                'effective_from',
                'ended_at',
            ])
            ->withTimestamps()
            ->wherePivotNull('ended_at');
    }

    /**
     * @return HasMany<GuardianStudent, $this>
     */
    public function guardianLinks(): HasMany
    {
        return $this->hasMany(GuardianStudent::class, 'student_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'student_id');
    }

    public function potentialDuplicateEnrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'potential_duplicate_student_id');
    }

    /** @return HasMany<StudentProfileCorrection, $this> */
    public function corrections(): HasMany
    {
        return $this->hasMany(StudentProfileCorrection::class, 'student_id');
    }

    /**
     * The most recent application, used by the directory listing to show the
     * learner's current grade, year, and state without loading every record.
     *
     * @return HasOne<Enrollment, $this>
     */
    public function latestEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class, 'student_id')->latestOfMany();
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name, $this->extension_name])
            ->filter()
            ->join(' ');
    }

    public function getFatherFullNameAttribute(): string
    {
        return collect([$this->father_first_name, $this->father_middle_name, $this->father_last_name, $this->father_extension_name])
            ->filter(fn ($value) => filled($value))
            ->join(' ');
    }

    public function getMotherFullNameAttribute(): string
    {
        return collect([$this->mother_first_name, $this->mother_middle_name, $this->mother_maiden_last_name, $this->mother_extension_name])
            ->filter(fn ($value) => filled($value))
            ->join(' ');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
