<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ExamPeriod extends Model
{
    protected $fillable = [
        'academic_year_id',
        'code',
        'name',
        'starts_on',
        'ends_on',
        'eligibility_cutoff_on',
        'issue_opens_on',
        'issue_closes_on',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(
            fn (self $model) =>
                $model->public_id ??= (string) Str::ulid()
        );
    }

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'eligibility_cutoff_on' => 'date',
            'issue_opens_on' => 'date',
            'issue_closes_on' => 'date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function getPaymentCutoffStatusAttribute(): string
    {
        if (! $this->eligibility_cutoff_on) {
            return 'not_set';
        }

        return today()->gt($this->eligibility_cutoff_on)
            ? 'closed'
            : 'open';
    }

    public function getEffectiveStatusAttribute(): string
    {
        if ($this->status === 'closed') {
            return 'closed';
        }

        if ($this->starts_on && today()->lt($this->starts_on)) {
            return 'planned';
        }

        if ($this->ends_on && today()->gt($this->ends_on)) {
            return 'closed';
        }

        return 'open';
    }

    public function getPermitIssuanceStatusAttribute(): string
    {
        if (
            $this->issue_opens_on &&
            today()->lt($this->issue_opens_on)
        ) {
            return 'not_yet_open';
        }

        if (
            $this->issue_closes_on &&
            today()->gt($this->issue_closes_on)
        ) {
            return 'closed';
        }

        return 'open';
    }
}
