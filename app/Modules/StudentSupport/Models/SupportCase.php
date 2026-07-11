<?php

namespace App\Modules\StudentSupport\Models;

use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SupportCase extends Model
{
    protected $fillable = ['enrollment_id', 'status', 'opened_by', 'opened_at', 'assigned_reviewer_id', 'closed_by', 'closed_at', 'final_outcome'];

    protected static function booted(): void
    {
        static::creating(fn (self $record) => $record->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(SupportAssessment::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(SupportObservation::class);
    }

    public function summaries(): HasMany
    {
        return $this->hasMany(ApprovedSupportSummary::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
