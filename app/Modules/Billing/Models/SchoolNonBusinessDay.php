<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolNonBusinessDay extends Model
{
    protected $fillable = ['academic_year_id', 'calendar_date', 'label'];

    protected function casts(): array
    {
        return ['calendar_date' => 'date'];
    }
}
