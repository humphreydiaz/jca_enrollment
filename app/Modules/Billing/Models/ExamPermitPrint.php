<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class ExamPermitPrint extends Model
{
    protected $fillable = ['exam_permit_id', 'print_number', 'printed_by', 'printed_at'];

    protected function casts(): array
    {
        return ['printed_at' => 'datetime'];
    }
}
