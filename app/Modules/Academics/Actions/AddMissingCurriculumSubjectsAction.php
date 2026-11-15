<?php

namespace App\Modules\Academics\Actions;

use App\Models\User;
use App\Modules\Academics\Models\Curriculum;
use App\Modules\Academics\Models\CurriculumGradeSubject;
use App\Modules\Academics\Models\CurriculumSectionTemplate;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Support\ClassSchedule;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Adds the published curriculum's grade subjects that a section does not offer yet. It never
 * removes, archives, or reschedules an existing offering; archived offerings are left archived.
 */
class AddMissingCurriculumSubjectsAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(Section $section, User $actor, bool $recordWhenEmpty = true): int
    {
        return DB::transaction(function () use ($section, $actor, $recordWhenEmpty): int {
            $section = Section::query()->lockForUpdate()->findOrFail($section->id);
            $curriculum = Curriculum::query()
                ->where('academic_year_id', $section->academic_year_id)
                ->where('status', 'published')
                ->first();
            if ($curriculum === null) {
                return 0;
            }

            $template = CurriculumSectionTemplate::query()->with('schedules')
                ->where('curriculum_id', $curriculum->id)
                ->where('grade_level_id', $section->grade_level_id)
                ->where('name', $section->name)
                ->first();
            $existing = SectionSubject::query()->where('section_id', $section->id)->pluck('subject_id')->all();
            $added = 0;
            $mappings = CurriculumGradeSubject::query()
                ->where('curriculum_id', $curriculum->id)
                ->where('grade_level_id', $section->grade_level_id)
                ->orderBy('sort_order')
                ->get();
            foreach ($mappings as $mapping) {
                if (in_array($mapping->subject_id, $existing, true)) {
                    continue;
                }
                $timetable = $template?->schedules->firstWhere('subject_id', $mapping->subject_id);
                $schedule = $timetable ? ClassSchedule::fromRecord($timetable) : null;
                SectionSubject::query()->create([
                    'section_id' => $section->id,
                    'subject_id' => $mapping->subject_id,
                    'curriculum_id' => $curriculum->id,
                    'status' => 'active',
                    ...($schedule?->attributes() ?? ['schedule_text' => $timetable?->schedule_text]),
                ]);
                $added++;
            }

            if ($added > 0 || $recordWhenEmpty) {
                $this->audit->record($actor, 'academics.section.curriculum_subjects_added', $section, [
                    'curriculum_id' => $curriculum->id,
                    'added' => $added,
                ]);
            }

            return $added;
        });
    }
}
