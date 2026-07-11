<?php

namespace App\Modules\Identity\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'is_staff',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_staff' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot(['id', 'assigned_by', 'assigned_at', 'revoked_by', 'revoked_at'])
            ->wherePivotNull('revoked_at');
    }

    /**
     * @return HasMany<UserRole, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }
}
