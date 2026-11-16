<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Reporting\Analytics\SchoolInsightsQuery;
use App\Modules\Reporting\Http\Requests\SchoolInsightsRequest;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\View\View;

/** D-065: School insights for the Admin/owner and Accounting (enrollment and collections, this year vs last). */
class SchoolInsightsController
{
    public function show(SchoolInsightsRequest $request, SchoolInsightsQuery $insights, AuditRecorder $audit): View
    {
        return $this->render($request, $insights, $audit, 'reporting.analytics.index');
    }

    public function print(SchoolInsightsRequest $request, SchoolInsightsQuery $insights, AuditRecorder $audit): View
    {
        return $this->render($request, $insights, $audit, 'reporting.analytics.print');
    }

    private function render(SchoolInsightsRequest $request, SchoolInsightsQuery $insights, AuditRecorder $audit, string $view): View
    {
        $year = $request->academicYear();
        $data = $year ? $insights->for($year) : null;
        $audit->record($request->user(), 'reporting.analytics.viewed', null, ['school_year' => $year?->public_id, 'print' => $view === 'reporting.analytics.print']);

        return view($view, [
            'insights' => $data,
            'years' => AcademicYear::query()->orderByDesc('starts_on')->get(['public_id', 'name']),
        ]);
    }
}
