<?php

namespace App\Modules\Academics\Models;

use App\Modules\Academics\Support\ClassSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumTemplateSchedule extends Model
{
    protected $fillable = ['curriculum_section_template_id', 'subject_id', 'days', 'starts_at', 'ends_at', 'schedule_text'];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classSchedule(): ?ClassSchedule
    {
        return ClassSchedule::fromRecord($this);
    }
}
