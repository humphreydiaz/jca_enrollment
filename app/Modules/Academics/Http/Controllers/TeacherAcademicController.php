<?php

namespace App\Modules\Academics\Http\Controllers;

use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Academics\Queries\TeacherAssignmentsQuery;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TeacherAcademicController
{
    public function __invoke(TeacherAssignmentsQuery $assignments): View
    {
        Gate::authorize('viewAny', TeachingAssignment::class);

        $records = $assignments->forUser(request()->user());
        $studentsBySection = SectionAssignment::query()->whereIn('section_id', $records->pluck('section_id')->unique())->where('status', 'confirmed')->whereNull('ended_at')->whereHas('enrollment', fn ($query) => $query->where('status', 'enrolled'))->with('enrollment.student')->get()->groupBy('section_id');

        return view('academics.teacher.assignments', ['assignments' => $records, 'studentsBySection' => $studentsBySection]);
    }
}
