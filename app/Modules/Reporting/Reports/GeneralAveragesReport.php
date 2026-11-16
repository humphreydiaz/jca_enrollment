<?php

namespace App\Modules\Reporting\Reports;

use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * D-069 "General averages" report (pre-final panel: "overall/report list of grades in all levels // sa grades
 * dapat may filtering yung naka range into ave like 75-80, 80-85 ganon, in order"). One row per learner and
 * school year from Registrar-approved final grades only (D-038): number of approved subjects, the average of
 * those final grades, the lowest one, and the rank in section and in grade level. Ranks are computed over the
 * whole year before any filter, so narrowing the list never renumbers it. The average is a plain mean of
 * approved final grades, not an official DepEd general average or an honors decision.
 */
class GeneralAveragesReport
{
    public function query(): Builder
    {
        $grades = DB::table('student_subject_grades')->where('status', 'finalized')->whereNotNull('final_grade')
            ->groupBy('enrollment_id')
            ->selectRaw('enrollment_id, COUNT(*) as subjects, AVG(final_grade) as average, MIN(final_grade) as lowest');

        $ranked = DB::table('enrollments as e')
            ->joinSub($grades, 'ga', 'ga.enrollment_id', '=', 'e.id')
            ->join('student_profiles as s', 's.id', '=', 'e.student_id')
            ->join('academic_years as ay', 'ay.id', '=', 'e.academic_year_id')
            ->join('grade_levels as g', 'g.id', '=', 'e.grade_level_id')
            ->leftJoin('section_assignments as sa', fn ($join) => $join->on('sa.enrollment_id', '=', 'e.id')->where('sa.status', 'confirmed')->whereNull('sa.ended_at'))
            ->leftJoin('sections as sec', 'sec.id', '=', 'sa.section_id')
            ->whereNotIn('e.status', [...Enrollment::TERMINAL_STATUSES, 'draft'])
            ->selectRaw("s.student_number, CONCAT(s.last_name, ', ', s.first_name) as student_name, ay.name as school_year, g.name as grade, sec.name as section,
                ga.subjects, ROUND(ga.average, 2) as general_average, ga.lowest as lowest_grade,
                RANK() OVER (PARTITION BY e.academic_year_id, sa.section_id ORDER BY ga.average DESC) as rank_in_section,
                RANK() OVER (PARTITION BY e.academic_year_id, e.grade_level_id ORDER BY ga.average DESC) as rank_in_grade,
                ay.public_id as year_public, g.public_id as grade_public, sec.public_id as section_public, sec.id as section_id,
                s.first_name, s.last_name, e.public_id as row_key, ay.starts_on as year_starts, g.sequence as grade_sequence");

        return DB::query()->fromSub($ranked, 'rt')->select('rt.*')
            ->orderByDesc('rt.year_starts')->orderBy('rt.grade_sequence')->orderBy('rt.rank_in_grade')->orderBy('rt.student_name');
    }
}
