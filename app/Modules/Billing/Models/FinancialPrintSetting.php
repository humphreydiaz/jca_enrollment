<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialPrintSetting extends Model
{
    protected $fillable = ['academic_year_id', 'setting_key', 'setting_value', 'updated_by'];
}
