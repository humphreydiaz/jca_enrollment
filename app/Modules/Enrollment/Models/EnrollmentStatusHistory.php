<?php

namespace App\Modules\Enrollment\Models;

use Illuminate\Database\Eloquent\Model;

class EnrollmentStatusHistory extends Model
{
    protected $table = 'enrollment_status_history';

    protected $fillable = ['enrollment_id', 'from_status', 'to_status', 'reason', 'changed_by', 'metadata', 'changed_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'changed_at' => 'datetime'];
    }
}
