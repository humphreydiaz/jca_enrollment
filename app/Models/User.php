<?php

namespace App\Models;

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\AccountInvitation;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\UserRole;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StaffProfile;
use App\Modules\People\Models\StudentProfile;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'name',
        'email',
        'mobile',
        'password',
        'status',
        'must_change_password',
        'password_changed_at',
        'temporary_access_expires_at',
        'temporary_access_used_at',
        'last_login_at',
        'locked_at',
        'disabled_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            $user->public_id ??= (string) Str::ulid();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'temporary_access_expires_at' => 'datetime',
            'temporary_access_used_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_at' => 'datetime',
            'disabled_at' => 'datetime',
            'status' => AccountStatus::class,
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['id', 'assigned_by', 'assigned_at', 'revoked_by', 'revoked_at'])
            ->wherePivotNull('revoked_at');
    }

    /**
     * @return HasMany<UserRole, $this>
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /**
     * @return HasMany<AccountInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(AccountInvitation::class);
    }

    /**
     * @return HasOne<GuardianProfile, $this>
     */
    public function guardianProfile(): HasOne
    {
        return $this->hasOne(GuardianProfile::class);
    }

    /**
     * @return HasOne<StudentProfile, $this>
     */
    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    /**
     * @return HasOne<StaffProfile, $this>
     */
    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function hasRole(string $roleCode): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('code', $roleCode);
        }

        return $this->roles()->where('code', $roleCode)->exists();
    }

    public function hasAnyRole(array $roleCodes): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->pluck('code')->intersect($roleCodes)->isNotEmpty();
        }

        return $this->roles()->whereIn('code', $roleCodes)->exists();
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active
            && $this->locked_at === null
            && $this->disabled_at === null;
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
