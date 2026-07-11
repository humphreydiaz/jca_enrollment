<?php

namespace App\Modules\StudentSupport\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovedSupportSummary extends Model
{
    protected $fillable = ['support_case_id', 'summary', 'approved_by', 'approved_at', 'effective_from', 'effective_until', 'superseded_by'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'effective_from' => 'date', 'effective_until' => 'date'];
    }
}
