<?php

namespace App\Modules\People\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'first_name',
        'last_name',
        'middle_name',
        'preferred_name',
        'birth_date',
        'status',
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

    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter()
            ->join(' ');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
