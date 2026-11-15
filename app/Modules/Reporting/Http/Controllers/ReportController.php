<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Reporting\Actions\DeleteReportExportAction;
use App\Modules\Reporting\Actions\RequestReportExportAction;
use App\Modules\Reporting\Http\Requests\ReportFilterRequest;
use App\Modules\Reporting\Models\ExportJob;
use App\Modules\Reporting\Reports\ReportCatalog;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController
{
    public function index(Request $request, ReportCatalog $reports): View
    {
        $visible = $reports->visibleTo($request->user());

        return view('reporting.index', [
            'reports' => $visible,
            'exports' => ExportJob::query()->where('requested_by', $request->user()->id)->whereIn('report_code', array_keys($visible))->latest()->limit(20)->get(),
        ]);
    }

    public function show(ReportFilterRequest $request, string $report, ReportCatalog $reports, AuditRecorder $audit): View
    {
        $filters = $request->filters();

        $viewData = [
            'reportCode' => $report,
            'definition' => $reports->definitions()[$report],
            'columns' => $reports->columns($report, $request->user()),
            'rows' => $reports->query($report, $request->user(), $filters)->paginate(25)->withQueryString(),
            'filters' => $filters,
            'allowedFilters' => $reports->filters($report),
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'gradeLevels' => GradeLevel::query()->where('is_active', true)->orderBy('sequence')->get(),
            'sections' => $this->sectionsFor($request),
        ];
        if ($report === 'grades') {
            $audit->record($request->user(), 'grading.report.viewed');
        }

        if ($report === 'enrollments') {
            $viewData['enrollmentSummary'] = $reports->enrollmentSummary($filters);
        }

        return view('reporting.show', $viewData);
    }

    private function sectionsFor(Request $request): Collection
    {
        $query = Section::query()->with(['academicYear', 'gradeLevel'])->orderBy('name');
        if ($request->user()->hasRole('teacher') && ! $request->user()->hasAnyRole(['admin', 'registrar'])) {
            $userId = $request->user()->id;
            $query->whereHas('teachingAssignments', fn ($assignments) => $assignments->active()->whereHas('staffProfile', fn ($staff) => $staff->where('user_id', $userId)));
        }

        return $query->get();
    }

    public function print(ReportFilterRequest $request, string $report, ReportCatalog $reports, AuditRecorder $audit): View
    {
        $filters = $request->filters();
        $limit = (int) config('reporting.print_row_limit');
        $rows = $reports->query($report, $request->user(), $filters)->limit($limit + 1)->get();
        $truncated = $rows->count() > $limit;
        $audit->record($request->user(), 'reporting.report.printed', null, ['report_code' => $report, 'row_count' => min($rows->count(), $limit)]);

        $viewData = ['definition' => $reports->definitions()[$report], 'columns' => $reports->columns($report, $request->user()), 'rows' => $rows->take($limit), 'filters' => $filters, 'truncated' => $truncated, 'reportCode' => $report];

        if ($report === 'enrollments') {
            $viewData['enrollmentSummary'] = $reports->enrollmentSummary($filters);
        }

        return view('reporting.print', $viewData);
    }

    public function export(ReportFilterRequest $request, string $report, RequestReportExportAction $action): RedirectResponse
    {
        $action->execute($report, $request->filters(), $request->user());

        return to_route('reporting.index')->with('success', 'Private CSV export queued.');
    }

    public function download(ExportJob $export, Request $request, AuditRecorder $audit): StreamedResponse
    {
        if (! Gate::allows('view', $export)) {
            abort(404);
        }
        abort_if($export->expires_at->isPast() || $export->deleted_at !== null, 410, 'This export is no longer available.');
        abort_unless($export->status === 'completed' && $export->storage_path !== null && Storage::disk($export->disk)->exists($export->storage_path), 404);
        $export->increment('download_count');
        $export->update(['last_downloaded_at' => now()]);
        $audit->record($request->user(), 'reporting.export.downloaded', $export, ['report_code' => $export->report_code]);

        return Storage::disk($export->disk)->download($export->storage_path, $export->download_name, ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function destroy(ExportJob $export, Request $request, DeleteReportExportAction $action): RedirectResponse
    {
        Gate::authorize('delete', $export);
        $action->execute($export, $request->user());

        return back()->with('success', 'Export file deleted. Its audit metadata was retained.');
    }
}
