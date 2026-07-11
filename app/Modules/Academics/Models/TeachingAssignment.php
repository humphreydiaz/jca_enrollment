<?php

namespace App\Modules\Academics\Models;

use App\Models\User;
use App\Modules\People\Models\StaffProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TeachingAssignment extends Model
{
    protected $fillable = ['staff_profile_id', 'section_id', 'section_subject_id', 'assignment_type', 'active_from', 'active_until', 'is_active'];

    protected static function booted(): void
    {
        static::creating(fn (self $assignment) => $assignment->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['active_from' => 'date', 'active_until' => 'date', 'is_active' => 'boolean'];
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function sectionSubject(): BelongsTo
    {
        return $this->belongsTo(SectionSubject::class);
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $dates) => $dates->whereNull('active_from')->orWhereDate('active_from', '<=', today()))
            ->where(fn (Builder $dates) => $dates->whereNull('active_until')->orWhereDate('active_until', '>=', today()));
    }

    /** @param Builder<self> $query */
    public function scopeForUser(Builder $query, User $user): void
    {
        $query->whereHas('staffProfile', fn (Builder $staff) => $staff->where('user_id', $user->id));
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
