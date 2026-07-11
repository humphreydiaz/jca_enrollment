<?php

namespace App\Modules\SectionAssignment\Models;

use App\Models\User;
use App\Modules\Academics\Models\Section;
use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SectionAssignment extends Model
{
    protected $fillable = [
        'enrollment_id', 'section_id', 'status', 'active_assignment_key', 'confirmed_by', 'confirmed_at',
        'effective_from', 'ended_at', 'ended_by', 'ended_reason', 'transferred_from_id',
        'support_suitability_confirmed', 'suitability_confirmed_by', 'suitability_confirmed_at',
        'is_capacity_override', 'capacity_override_reason', 'capacity_override_by',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $assignment) => $assignment->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime', 'effective_from' => 'date', 'ended_at' => 'datetime',
            'support_suitability_confirmed' => 'boolean', 'suitability_confirmed_at' => 'datetime',
            'is_capacity_override' => 'boolean',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function transferredFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'transferred_from_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SectionAssignmentEvent::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
