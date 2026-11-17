<?php

namespace App\Modules\Academics\Actions;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\Curriculum;
use App\Modules\Academics\Models\CurriculumGradeSubject;
use App\Modules\Academics\Models\CurriculumSectionTemplate;
use App\Modules\Academics\Models\CurriculumTemplateSchedule;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteCurriculumDraftAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Curriculum $curriculum, User $actor): void
    {
        DB::transaction(function () use ($curriculum, $actor): void {
            AcademicYear::query()->lockForUpdate()->findOrFail($curriculum->academic_year_id);
            $draft = Curriculum::query()->lockForUpdate()->findOrFail($curriculum->id);

            if ($draft->status !== 'draft' || $draft->published_at !== null || $draft->published_by !== null) {
                throw ValidationException::withMessages(['curriculum' => 'Only an unpublished curriculum draft can be deleted. Published history must be kept.']);
            }

            if (Section::query()->where('curriculum_id', $draft->id)->exists()
                || SectionSubject::query()->where('curriculum_id', $draft->id)->exists()) {
                throw ValidationException::withMessages(['curriculum' => 'This curriculum is linked to school-year sections or subject offerings and cannot be deleted.']);
            }

            $templateIds = CurriculumSectionTemplate::query()->where('curriculum_id', $draft->id)->pluck('id');
            $subjectCount = CurriculumGradeSubject::query()->where('curriculum_id', $draft->id)->count();
            $sectionCount = $templateIds->count();
            $scheduleCount = CurriculumTemplateSchedule::query()->whereIn('curriculum_section_template_id', $templateIds)->count();

            CurriculumTemplateSchedule::query()->whereIn('curriculum_section_template_id', $templateIds)->delete();
            CurriculumSectionTemplate::query()->where('curriculum_id', $draft->id)->delete();
            CurriculumGradeSubject::query()->where('curriculum_id', $draft->id)->delete();

            $this->audit->record($actor, 'academics.curriculum.draft_deleted', $draft, [
                'year_id' => $draft->academic_year_id,
                'version' => $draft->version,
                'grade_subjects' => $subjectCount,
                'section_templates' => $sectionCount,
                'schedule_templates' => $scheduleCount,
            ]);

            $draft->delete();
        });
    }
}
