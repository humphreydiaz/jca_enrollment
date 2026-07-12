<?php

namespace App\Modules\StudentSupport\Models;

use Illuminate\Database\Eloquent\Model;

class SupportAssessment extends Model
{
    protected $fillable = ['support_case_id', 'assessment_type', 'completed_on', 'reviewer_id', 'outcome', 'confidential_rationale', 'next_action'];

    protected function casts(): array
    {
        return ['completed_on' => 'date'];
    }
}
