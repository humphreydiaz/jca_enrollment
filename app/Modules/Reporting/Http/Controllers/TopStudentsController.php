<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Reporting\Analytics\TopStudentsQuery;
use App\Modules\Reporting\Http\Requests\TopStudentsRequest;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\View\View;

/** D-069: top 10 students per grade level or section, from approved finals or projected from submitted grades. */
class TopStudentsController
{
    public function __invoke(TopStudentsRequest $request, TopStudentsQuery $top, AuditRecorder $audit): View
    {
        $year = $request->academicYear();
        $audit->record($request->user(), 'reporting.top_students.viewed', null, ['school_year' => $year?->public_id, 'basis' => $request->basis(), 'group' => $request->group()]);

        return view('reporting.top-students', [
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(['public_id', 'name']),
            'year' => $year,
            'group' => $request->group(),
            'basis' => $request->basis(),
            'result' => $year ? $top->for($year, $request->group(), $request->basis()) : null,
        ]);
    }
}
