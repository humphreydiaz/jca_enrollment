<?php

namespace App\Modules\Academics\Actions;

use App\Models\User;
use App\Modules\Academics\Models\Curriculum;
use App\Modules\Academics\Models\CurriculumGradeSubject;
use App\Modules\Academics\Models\CurriculumSectionTemplate;
use App\Modules\Academics\Models\CurriculumTemplateSchedule;
use App\Modules\Academics\Support\ClassSchedule;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveCurriculumMappingAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /** @param array<string, mixed> $data */
    public function subject(Curriculum $curriculum, array $data, User $actor): void
    {
        DB::transaction(function () use ($curriculum, $data, $actor): void {
            $this->lockDraft($curriculum);
            $mapping = CurriculumGradeSubject::query()->firstOrCreate(
                ['curriculum_id' => $curriculum->id, 'grade_level_id' => $data['grade_level_id'], 'subject_id' => $data['subject_id']],
                ['sort_order' => $data['sort_order'] ?? 0],
            );
            $this->audit->record($actor, 'academics.curriculum.subject_added', $curriculum, ['mapping_id' => $mapping->id]);
        });
    }

    /** @param array<string, mixed> $data */
    public function section(Curriculum $curriculum, array $data, User $actor): void
    {
        DB::transaction(function () use ($curriculum, $data, $actor): void {
            $this->lockDraft($curriculum);
            $template = CurriculumSectionTemplate::query()->firstOrNew([
                'curriculum_id' => $curriculum->id,
                'grade_level_id' => $data['grade_level_id'],
                'name' => $data['name'],
            ]);
            $template->fill([
                'capacity' => $data['capacity'],
                'sort_order' => $data['sort_order'] ?? 0,
                'activity_schedule' => $data['activity_schedule'] ?? null,
            ])->save();
            $this->audit->record($actor, 'academics.curriculum.section_saved', $curriculum, ['template_id' => $template->id]);
        });
    }

    /** @param array<string, mixed> $data */
    public function schedule(Curriculum $curriculum, CurriculumSectionTemplate $template, array $data, User $actor): void
    {
        DB::transaction(function () use ($curriculum, $template, $data, $actor): void {
            $this->lockDraft($curriculum);
            if ($template->curriculum_id !== $curriculum->id || ! CurriculumGradeSubject::query()
                ->where('curriculum_id', $curriculum->id)
                ->where('grade_level_id', $template->grade_level_id)
                ->where('subject_id', $data['subject_id'])->exists()) {
                throw ValidationException::withMessages(['subject_id' => 'Choose a subject mapped to this section’s grade.']);
            }
            $schedule = CurriculumTemplateSchedule::query()->updateOrCreate(
                ['curriculum_section_template_id' => $template->id, 'subject_id' => $data['subject_id']],
                ClassSchedule::attributesFromInput($data),
            );
            $this->audit->record($actor, 'academics.curriculum.schedule_saved', $curriculum, ['schedule_id' => $schedule->id]);
        });
    }

    public function removeSubject(Curriculum $curriculum, int $mappingId, User $actor): void
    {
        DB::transaction(function () use ($curriculum, $mappingId, $actor): void {
            $this->lockDraft($curriculum);
            $mapping = CurriculumGradeSubject::query()->where('curriculum_id', $curriculum->id)->findOrFail($mappingId);
            CurriculumTemplateSchedule::query()->whereIn('curriculum_section_template_id',
                CurriculumSectionTemplate::query()->where('curriculum_id', $curriculum->id)->where('grade_level_id', $mapping->grade_level_id)->select('id'))
                ->where('subject_id', $mapping->subject_id)->delete();
            $mapping->delete();
            $this->audit->record($actor, 'academics.curriculum.subject_removed', $curriculum, ['mapping_id' => $mappingId]);
        });
    }

    public function removeSection(Curriculum $curriculum, CurriculumSectionTemplate $template, User $actor): void
    {
        DB::transaction(function () use ($curriculum, $template, $actor): void {
            $this->lockDraft($curriculum);
            if ($template->curriculum_id !== $curriculum->id) {
                abort(404);
            }
            $template->schedules()->delete();
            $template->delete();
            $this->audit->record($actor, 'academics.curriculum.section_removed', $curriculum, ['template_id' => $template->id]);
        });
    }

    private function lockDraft(Curriculum $curriculum): void
    {
        if (Curriculum::query()->lockForUpdate()->findOrFail($curriculum->id)->status !== 'draft') {
            throw ValidationException::withMessages(['curriculum' => 'Published curricula cannot be edited. Create a new version.']);
        }
    }
}
