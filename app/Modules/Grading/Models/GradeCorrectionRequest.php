<?php

namespace App\Modules\Grading\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GradeCorrectionRequest extends Model
{
    protected $fillable = ['student_subject_grade_id', 'requested_by', 'previous_status', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected static function booted(): void
    {
        static::creating(fn (self $request) => $request->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(StudentSubjectGrade::class, 'student_subject_grade_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
