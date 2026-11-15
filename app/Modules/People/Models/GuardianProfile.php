<?php

namespace App\Modules\People\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class GuardianProfile extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'user_id',
        'first_name',
        'last_name',
        'middle_name',
        'extension_name',
        'mobile',
        'email',
        'preferred_contact_method',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $guardian): void {
            $guardian->public_id ??= (string) Str::ulid();
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<StudentProfile, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(StudentProfile::class, 'guardian_student', 'guardian_id', 'student_id')
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
    public function studentLinks(): HasMany
    {
        return $this->hasMany(GuardianStudent::class, 'guardian_id');
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name, $this->extension_name])
            ->filter()
            ->join(' ');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
