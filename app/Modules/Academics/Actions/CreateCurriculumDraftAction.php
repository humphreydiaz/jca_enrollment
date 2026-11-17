<?php

namespace App\Modules\Academics\Actions;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\Curriculum;
use App\Modules\Academics\Models\CurriculumGradeSubject;
use App\Modules\Academics\Models\CurriculumSectionTemplate;
use App\Modules\Academics\Models\CurriculumTemplateSchedule;
use App\Modules\Academics\Support\OfficialClassProgramTemplate;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCurriculumDraftAction
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly OfficialClassProgramTemplate $officialTemplate,
    ) {}

    public function execute(AcademicYear $year, string $name, ?Curriculum $source, User $actor, bool $useOfficialDefault = false): Curriculum
    {
        return DB::transaction(function () use ($year, $name, $source, $actor, $useOfficialDefault): Curriculum {
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($year->id);
            if (! in_array($year->status, ['planned', 'open'], true)) {
                throw ValidationException::withMessages(['academic_year_id' => 'Choose a planned or open school year.']);
            }
            if ($source !== null && $source->status !== 'published') {
                throw ValidationException::withMessages(['source_curriculum_id' => 'Only a published curriculum can be copied.']);
            }
            if ($source !== null && $useOfficialDefault) {
                throw ValidationException::withMessages(['source_curriculum_id' => 'Choose one curriculum source.']);
            }

            $version = (int) Curriculum::query()->where('academic_year_id', $year->id)->max('version') + 1;
            $curriculum = Curriculum::query()->create([
                'academic_year_id' => $year->id,
                'version' => $version,
                'name' => $name,
                'status' => 'draft',
                'source_note' => $useOfficialDefault
                    ? 'Based on the owner-supplied 2026-2027 official class-program template; review schedules and staff before publishing.'
                    : ($source ? 'Copied from '.$source->academicYear->code.' version '.$source->version : null),
            ]);

            if ($useOfficialDefault) {
                $this->officialTemplate->populate($curriculum);
            } elseif ($source !== null) {
                $source->load(['gradeSubjects', 'sectionTemplates.schedules']);
                foreach ($source->gradeSubjects as $mapping) {
                    CurriculumGradeSubject::query()->create([
                        'curriculum_id' => $curriculum->id,
                        'grade_level_id' => $mapping->grade_level_id,
                        'subject_id' => $mapping->subject_id,
                        'sort_order' => $mapping->sort_order,
                    ]);
                }
                foreach ($source->sectionTemplates as $template) {
                    $copy = CurriculumSectionTemplate::query()->create([
                        'curriculum_id' => $curriculum->id,
                        'grade_level_id' => $template->grade_level_id,
                        'name' => $template->name,
                        'capacity' => $template->capacity,
                        'sort_order' => $template->sort_order,
                        'activity_schedule' => $template->activity_schedule,
                    ]);
                    foreach ($template->schedules as $schedule) {
                        CurriculumTemplateSchedule::query()->create([
                            'curriculum_section_template_id' => $copy->id,
                            'subject_id' => $schedule->subject_id,
                            'days' => $schedule->days,
                            'starts_at' => $schedule->starts_at,
                            'ends_at' => $schedule->ends_at,
                            'schedule_text' => $schedule->schedule_text,
                        ]);
                    }
                }
            }

            $this->audit->record($actor, 'academics.curriculum.draft_created', $curriculum, [
                'source_curriculum_id' => $source?->id,
                'source_template' => $useOfficialDefault ? OfficialClassProgramTemplate::SOURCE_KEY : null,
            ]);

            return $curriculum;
        });
    }
}
