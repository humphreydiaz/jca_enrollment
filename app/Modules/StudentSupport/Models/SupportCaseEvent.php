<?php

namespace App\Modules\StudentSupport\Models;

use Illuminate\Database\Eloquent\Model;

class SupportCaseEvent extends Model
{
    protected $fillable = ['support_case_id', 'event_type', 'from_status', 'to_status', 'actor_user_id', 'reviewer_user_id', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
