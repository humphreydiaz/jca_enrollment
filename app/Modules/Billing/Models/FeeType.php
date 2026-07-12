<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class FeeType extends Model
{
    protected $fillable = ['code', 'name', 'category', 'is_mandatory', 'is_conditional', 'is_permit_critical', 'max_quantity', 'is_active'];

    protected function casts(): array
    {
        return ['is_mandatory' => 'boolean', 'is_conditional' => 'boolean', 'is_permit_critical' => 'boolean', 'is_active' => 'boolean'];
    }
}
