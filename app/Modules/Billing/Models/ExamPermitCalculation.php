<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ExamPermitCalculation extends Model
{
    protected $fillable = ['enrollment_id', 'exam_period_id', 'fee_assessment_id', 'required_amount', 'satisfied_amount', 'ordinary_eligible', 'override_eligible', 'calculated_at', 'calculated_by'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['required_amount' => 'decimal:2', 'satisfied_amount' => 'decimal:2', 'ordinary_eligible' => 'boolean', 'override_eligible' => 'boolean', 'calculated_at' => 'datetime'];
    }
}
