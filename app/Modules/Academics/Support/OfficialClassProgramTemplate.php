<?php

namespace App\Modules\Academics\Support;

use App\Modules\Academics\Models\Curriculum;
use App\Modules\Academics\Models\CurriculumGradeSubject;
use App\Modules\Academics\Models\CurriculumSectionTemplate;
use App\Modules\Academics\Models\CurriculumTemplateSchedule;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Subject;
use Illuminate\Validation\ValidationException;

/** Shared, owner-supplied class-program template. Every use creates a new reviewable draft. */
class OfficialClassProgramTemplate
{
    public const SOURCE_KEY = 'official-default';

    public function populate(Curriculum $curriculum): void
    {
        $program = config('jca_official_class_program');
        $gradeSubjects = $program['grade_subjects'];
        $preschool = $program['preschool'];
        $grades = GradeLevel::query()->whereIn('code', array_merge(array_keys($gradeSubjects), array_keys($preschool)))->get()->keyBy('code');
        if ($grades->count() !== 13) {
            throw ValidationException::withMessages(['source_curriculum_id' => 'The default curriculum needs the complete Nursery through Grade 10 grade catalog.']);
        }
        $subjects = [];
        foreach ($program['subjects'] as $code => $name) {
            $subjects[$code] = Subject::query()->firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }
        foreach ($gradeSubjects as $gradeCode => $codes) {
            foreach ($codes as $index => $code) {
                CurriculumGradeSubject::query()->create([
                    'curriculum_id' => $curriculum->id,
                    'grade_level_id' => $grades[$gradeCode]->id,
                    'subject_id' => $subjects[$code]->id,
                    'sort_order' => $index + 1,
                ]);
            }
            $sectionOrder = 0;
            foreach ($program['section_times'][$gradeCode] as $name => $times) {
                $template = CurriculumSectionTemplate::query()->create([
                    'curriculum_id' => $curriculum->id,
                    'grade_level_id' => $grades[$gradeCode]->id,
                    'name' => $name,
                    'capacity' => 15,
                    'sort_order' => ++$sectionOrder,
                    'activity_schedule' => $gradeCode === 'G1' || $gradeCode === 'G2'
                        ? 'Monday-Thursday: Reading and Math enhancement, 12:40-13:40. Friday: HGP. Review printed HGP minutes before final timetable.'
                        : ($gradeCode === 'G3' ? 'Monday-Thursday: Reading and Math enhancement, 14:20-15:20. Friday: HGP.' : 'Monday-Thursday: Math and Reading enhancement, 15:05-15:35. Friday: HGP.'),
                ]);
                foreach ($times as $code => $time) {
                    CurriculumTemplateSchedule::query()->create([
                        'curriculum_section_template_id' => $template->id,
                        'subject_id' => $subjects[$code]->id,
                        ...ClassSchedule::parse('Monday-Friday '.$time)->attributes(),
                    ]);
                }
            }
        }
        foreach ($preschool as $gradeCode => $sections) {
            $sectionOrder = 0;
            foreach ($sections as $name => $program) {
                CurriculumSectionTemplate::query()->create([
                    'curriculum_id' => $curriculum->id,
                    'grade_level_id' => $grades[$gradeCode]->id,
                    'name' => $name,
                    'capacity' => 15,
                    'sort_order' => ++$sectionOrder,
                    'activity_schedule' => $program,
                ]);
            }
        }
    }
}
