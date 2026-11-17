<?php

namespace App\Modules\Grading\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeRevision extends Model
{
    protected $fillable = ['student_subject_grade_id', 'event_type', 'quarter_number', 'old_value', 'new_value', 'actor_user_id', 'reason', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(StudentSubjectGrade::class, 'student_subject_grade_id');
    }
}
