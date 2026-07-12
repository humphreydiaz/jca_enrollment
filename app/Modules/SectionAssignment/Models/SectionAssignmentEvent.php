<?php

namespace App\Modules\SectionAssignment\Models;

use Illuminate\Database\Eloquent\Model;

class SectionAssignmentEvent extends Model
{
    protected $fillable = ['section_assignment_id', 'enrollment_id', 'event_type', 'from_section_id', 'to_section_id', 'actor_user_id', 'reason', 'is_capacity_override', 'occurred_at'];

    protected function casts(): array
    {
        return ['is_capacity_override' => 'boolean', 'occurred_at' => 'datetime'];
    }
}
