<?php

namespace App\Modules\Academics\Actions;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\Curriculum;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishCurriculumAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Curriculum $curriculum, User $actor): void
    {
        DB::transaction(function () use ($curriculum, $actor): void {
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($curriculum->academic_year_id);
            $curriculum = Curriculum::query()->lockForUpdate()->findOrFail($curriculum->id);
            if ($curriculum->status !== 'draft' || ! in_array($year->status, ['planned', 'open'], true)) {
                throw ValidationException::withMessages(['curriculum' => 'Only a draft for a planned or open year can be published.']);
            }
            $curriculum->load(['gradeSubjects', 'sectionTemplates.schedules']);
            if ($curriculum->sectionTemplates->isEmpty()) {
                throw ValidationException::withMessages(['curriculum' => 'Add at least one section before publishing.']);
            }
            $mapped = $curriculum->gradeSubjects->groupBy('grade_level_id');
            foreach ($curriculum->sectionTemplates as $template) {
                if (! $mapped->has($template->grade_level_id) && $template->gradeLevel->school_stage !== 'Pre-elementary') {
                    throw ValidationException::withMessages(['curriculum' => 'Every school-age section needs grade subjects.']);
                }
                foreach ($template->schedules as $schedule) {
                    if (! $mapped->get($template->grade_level_id, collect())->contains('subject_id', $schedule->subject_id)) {
                        throw ValidationException::withMessages(['curriculum' => 'A section schedule refers to a subject outside its grade.']);
                    }
                }
            }

            $previous = Curriculum::query()->where('academic_year_id', $year->id)->where('status', 'published')->lockForUpdate()->first();
            if ($previous !== null) {
                throw ValidationException::withMessages(['curriculum' => 'This school year already has a published curriculum. Keep its history and create the next version for a new year.']);
            }

            foreach ($curriculum->sectionTemplates as $template) {
                $section = Section::query()->firstOrCreate(
                    ['academic_year_id' => $year->id, 'grade_level_id' => $template->grade_level_id, 'name' => $template->name],
                    ['curriculum_id' => $curriculum->id, 'capacity' => $template->capacity, 'sort_order' => $template->sort_order, 'status' => 'active', 'activity_schedule' => $template->activity_schedule],
                );
                if ($section->curriculum_id === null) {
                    $section->update(['curriculum_id' => $curriculum->id, 'activity_schedule' => $section->activity_schedule ?: $template->activity_schedule]);
                } elseif ($section->curriculum_id !== $curriculum->id) {
                    throw ValidationException::withMessages(['curriculum' => 'A matching section belongs to another curriculum.']);
                }
                foreach ($mapped->get($template->grade_level_id, collect()) as $mapping) {
                    $schedule = $template->schedules->firstWhere('subject_id', $mapping->subject_id);
                    $timetable = $schedule ? ['days' => $schedule->days, 'starts_at' => $schedule->starts_at, 'ends_at' => $schedule->ends_at, 'schedule_text' => $schedule->schedule_text] : [];
                    $sectionSubject = SectionSubject::query()->firstOrCreate(
                        ['section_id' => $section->id, 'subject_id' => $mapping->subject_id],
                        ['curriculum_id' => $curriculum->id, 'status' => 'active', ...$timetable],
                    );
                    if ($sectionSubject->curriculum_id === null) {
                        $sectionSubject->update(['curriculum_id' => $curriculum->id, ...($sectionSubject->schedule_text ? [] : $timetable)]);
                    } elseif ($sectionSubject->curriculum_id !== $curriculum->id) {
                        throw ValidationException::withMessages(['curriculum' => 'A matching section subject belongs to another curriculum.']);
                    }
                }
            }

            $curriculum->update(['status' => 'published', 'published_by' => $actor->id, 'published_at' => now()]);
            $this->audit->record($actor, 'academics.curriculum.published', $curriculum, [
                'year_id' => $year->id,
                'sections' => $curriculum->sectionTemplates->count(),
                'grade_subjects' => $curriculum->gradeSubjects->count(),
            ]);
        });
    }
}
