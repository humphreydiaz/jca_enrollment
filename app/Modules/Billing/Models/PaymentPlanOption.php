<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentPlanOption extends Model
{
    protected $fillable = ['academic_year_id', 'grade_level_id', 'code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
