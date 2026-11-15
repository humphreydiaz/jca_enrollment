<?php

namespace App\Modules\Academics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumSectionTemplate extends Model
{
    protected $fillable = ['curriculum_id', 'grade_level_id', 'name', 'capacity', 'sort_order', 'activity_schedule'];

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(CurriculumTemplateSchedule::class);
    }
}
