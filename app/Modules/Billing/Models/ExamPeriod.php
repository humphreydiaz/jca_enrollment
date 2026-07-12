<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ExamPeriod extends Model
{
    protected $fillable = ['academic_year_id', 'code', 'name', 'starts_on', 'ends_on', 'eligibility_cutoff_on', 'issue_opens_on', 'issue_closes_on', 'status'];

    protected static function booted(): void
    {
        static::creating(fn (self $m) => $m->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'eligibility_cutoff_on' => 'date', 'issue_opens_on' => 'date', 'issue_closes_on' => 'date'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
