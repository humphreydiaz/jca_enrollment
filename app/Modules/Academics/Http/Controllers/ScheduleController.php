<?php

namespace App\Modules\Academics\Http\Controllers;

use App\Modules\Academics\Http\Requests\ScheduleConflictsRequest;
use App\Modules\Academics\Http\Requests\TeacherWorkspaceRequest;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Academics\Queries\ScheduleConflictsQuery;
use App\Modules\Academics\Queries\TeacherAssignmentsQuery;
use App\Modules\Academics\Queries\TeacherWorkspaceQuery;
use App\Modules\Academics\Support\CurrentAcademicYear;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** D-068: schedule conflicts (Registrar/Admin) and the teacher's own weekly schedule. */
class ScheduleController
{
    public function conflicts(ScheduleConflictsRequest $request, ScheduleConflictsQuery $conflicts, CurrentAcademicYear $current): View
    {
        $years = AcademicYear::query()->orderByDesc('starts_on')->get();
        $year = $current->resolve($years, $request->validated('year')) ?? $years->first();

        return view('academics.admin.schedule-conflicts', [
            'years' => $years,
            'year' => $year,
            'result' => $year ? $conflicts->forYear($year) : null,
        ]);
    }

    public function mine(TeacherWorkspaceRequest $request, ScheduleConflictsQuery $conflicts, TeacherAssignmentsQuery $assignments, CurrentAcademicYear $current): View
    {
        Gate::authorize('viewAny', TeachingAssignment::class);
        $years = TeacherWorkspaceQuery::yearsOf($assignments->forUser($request->user()));
        $year = $current->resolve($years, $request->validated('year'));

        return view('academics.teacher.schedule', [
            'years' => $years,
            'year' => $year,
            'schedule' => $year ? $conflicts->forTeacher($request->user(), $year) : null,
        ]);
    }
}
