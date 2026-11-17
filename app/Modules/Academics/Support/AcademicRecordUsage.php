<?php

namespace App\Modules\Academics\Support;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Counts the records that still depend on an academic catalog row. A row with any usage must be
 * archived instead of deleted so enrollment, placement, grade, and finance history stays intact.
 */
class AcademicRecordUsage
{
    /** @return array<string, int> label => count, only non-zero entries */
    public function for(Model $record): array
    {
        return $this->forMany(collect([$record]))[$record->getKey()] ?? [];
    }

    /**
     * Usage for several records of the same type in a fixed number of grouped queries.
     *
     * @param  Collection<int, Model>  $records
     * @return array<int, array<string, int>> record id => usage
     */
    public function forMany(Collection $records): array
    {
        $first = $records->first();
        if ($first === null) {
            return [];
        }
        $ids = $records->map->getKey()->all();
        $usage = array_fill_keys($ids, []);
        foreach ($this->checks($first) as $label => $check) {
            $pairs = is_array($check[0]) ? $check : [$check];
            foreach ($pairs as [$table, $column]) {
                $counts = DB::table($table)->whereIn($column, $ids)
                    ->selectRaw($column.' as record_id, count(*) as total')
                    ->groupBy($column)->pluck('total', 'record_id');
                foreach ($counts as $id => $total) {
                    $usage[$id][$label] = ($usage[$id][$label] ?? 0) + (int) $total;
                }
            }
        }

        if ($first instanceof Section) {
            $grades = DB::table('student_subject_grades')
                ->join('section_subjects', 'section_subjects.id', '=', 'student_subject_grades.section_subject_id')
                ->whereIn('section_subjects.section_id', $ids)
                ->selectRaw('section_subjects.section_id as record_id, count(*) as total')
                ->groupBy('section_subjects.section_id')->pluck('total', 'record_id');
            foreach ($grades as $id => $total) {
                $usage[$id]['student grades'] = (int) $total;
            }
        }

        return $usage;
    }

    /** @return array<string, array{0: string, 1: string}|list<array{0: string, 1: string}>> */
    private function checks(Model $record): array
    {
        return match (true) {
            $record instanceof AcademicYear => [
                'enrollments' => ['enrollments', 'academic_year_id'],
                'sections' => ['sections', 'academic_year_id'],
                'curriculum versions' => ['curricula', 'academic_year_id'],
                'fee schedules' => ['fee_schedules', 'academic_year_id'],
                'payment plan options' => ['payment_plan_options', 'academic_year_id'],
                'discount campaigns' => ['discount_campaigns', 'academic_year_id'],
                'exam periods' => ['exam_periods', 'academic_year_id'],
                'school calendar days' => ['school_non_business_days', 'academic_year_id'],
                'print settings' => ['financial_print_settings', 'academic_year_id'],
                'grading periods' => ['grading_periods', 'academic_year_id'],
                'promotion recommendations' => ['promotion_recommendations', 'target_academic_year_id'],
            ],
            $record instanceof GradeLevel => [
                'enrollments' => ['enrollments', 'grade_level_id'],
                'sections' => ['sections', 'grade_level_id'],
                'curriculum subject mappings' => ['curriculum_grade_subjects', 'grade_level_id'],
                'curriculum section templates' => ['curriculum_section_templates', 'grade_level_id'],
                'fee schedules' => ['fee_schedules', 'grade_level_id'],
                'payment plan options' => ['payment_plan_options', 'grade_level_id'],
                'promotion recommendations' => ['promotion_recommendations', 'target_grade_level_id'],
                'grade levels promoting into it' => ['grade_levels', 'next_grade_level_id'],
            ],
            $record instanceof Subject => [
                'section offerings' => ['section_subjects', 'subject_id'],
                'curriculum subject mappings' => ['curriculum_grade_subjects', 'subject_id'],
                'curriculum timetable rows' => ['curriculum_template_schedules', 'subject_id'],
                'student grades' => ['student_subject_grades', 'subject_id'],
            ],
            $record instanceof Section => [
                'student placements' => ['section_assignments', 'section_id'],
                'placement history entries' => [['section_assignment_events', 'from_section_id'], ['section_assignment_events', 'to_section_id']],
                'teaching assignments' => ['teaching_assignments', 'section_id'],
            ],
            $record instanceof SectionSubject => [
                'teaching assignments' => ['teaching_assignments', 'section_subject_id'],
                'student grades' => ['student_subject_grades', 'section_subject_id'],
            ],
            $record instanceof TeachingAssignment => [
                'student grades' => ['student_subject_grades', 'teaching_assignment_id'],
            ],
            default => throw new InvalidArgumentException('Unsupported academic record.'),
        };
    }

    public function isUnused(Model $record): bool
    {
        return $this->for($record) === [];
    }

    /** @param array<string, int> $usage */
    public function describe(array $usage): string
    {
        return collect($usage)->map(fn (int $count, string $label): string => $count.' '.$label)->join(', ', ' and ');
    }
}
