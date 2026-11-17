<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Models\User;
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
        $exports = ExportJob::query()->where('requested_by', $request->user()->id)->whereIn('report_code', array_keys($visible))->latest()->limit(20)->get();

        return view('reporting.index', [
            'reports' => $visible,
            'exports' => $exports,
            // A completed row can outlive its file (a database copied to another server, or a
            // cleared storage folder). Offer the download only when the private file is really there.
            'availableExportIds' => $exports->filter(fn (ExportJob $export): bool => $this->fileAvailable($export))->pluck('id')->all(),
        ]);
    }

    public function show(ReportFilterRequest $request, string $report, ReportCatalog $reports, AuditRecorder $audit): View
    {
        $filters = $request->filters();
        $user = $request->user();

        $viewData = [
            'reportCode' => $report,
            'definition' => $reports->definitions()[$report],
            'allColumns' => $reports->columns($report, $user),
            'columns' => $reports->selectedColumns($report, $user, $request->pickedColumns()),
            'pickedColumns' => $request->pickedColumns(),
            'rows' => $reports->query($report, $user, $filters)->paginate($request->perPage())->withQueryString(),
            'perPage' => $request->perPage(),
            'filters' => $filters,
            'allowedFilters' => $reports->filters($report),
            'statusOptions' => in_array('status', $reports->filters($report), true) ? $reports->statusOptions($report) : [],
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'gradeLevels' => GradeLevel::query()->where('is_active', true)->orderBy('sequence')->get(),
            'sections' => $this->sectionsFor($request),
        ];
        if ($report === 'grades') {
            $audit->record($user, 'grading.report.viewed');
        }

        if ($report === 'enrollments') {
            $viewData['enrollmentSummary'] = $reports->enrollmentSummary($filters);
        }
        if ($report === 'retention') {
            $viewData['retentionSummary'] = $reports->retentionSummary($filters);
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

    /**
     * Prints the filtered report, or only the ticked rows, with only the picked columns
     * (panel: "print button ... para ito sa specific print na gusto mo lang ma print out").
     */
    public function print(ReportFilterRequest $request, string $report, ReportCatalog $reports, AuditRecorder $audit): View
    {
        $filters = $request->filters();
        $user = $request->user();
        $selectedRows = $request->pickedRows();
        $columns = $reports->selectedColumns($report, $user, $request->pickedColumns());
        $limit = (int) config('reporting.print_row_limit');
        // Ticked rows are still looked up inside the viewer's own scoped, filtered query.
        $rows = $reports->query($report, $user, $filters, $selectedRows)->limit($limit + 1)->get();
        $truncated = $rows->count() > $limit;
        $rows = $rows->take($limit);
        $audit->record($user, 'reporting.report.printed', null, [
            'report_code' => $report,
            'row_count' => $rows->count(),
            'selected_rows' => $selectedRows !== [],
            'fields' => array_keys($columns),
        ]);

        $totals = [];
        foreach (array_intersect(array_keys($columns), ReportCatalog::MONEY_COLUMNS) as $field) {
            $totals[$field] = $rows->sum(fn ($row) => (float) data_get($row, $field));
        }

        $viewData = [
            'definition' => $reports->definitions()[$report],
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $totals,
            'filters' => $filters,
            'filterSummary' => $this->filterSummary($filters, $reports, $report, $user),
            'selectedRows' => $selectedRows !== [],
            'truncated' => $truncated,
            'reportCode' => $report,
        ];

        // The enrollment report prints its official summary unless specific rows or columns were asked for.
        $wantsDetail = $selectedRows !== [] || $request->pickedColumns() !== null || $request->layout() === 'detail';
        if ($report === 'enrollments' && ! $wantsDetail) {
            $viewData['enrollmentSummary'] = $reports->enrollmentSummary($filters);
        }
        if ($report === 'retention' && $selectedRows === []) {
            $viewData['retentionSummary'] = $reports->retentionSummary($filters);
        }

        return view('reporting.print', $viewData);
    }

    /**
     * Human-readable "what is on this page" lines for the printout.
     *
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    private function filterSummary(array $filters, ReportCatalog $reports, string $report, User $user): array
    {
        $lines = [];
        if (filled($filters['school_year'] ?? null)) {
            $lines[] = 'School year: '.AcademicYear::query()->where('public_id', $filters['school_year'])->value('name');
        }
        if (filled($filters['grade'] ?? null)) {
            $lines[] = 'Grade: '.GradeLevel::query()->where('public_id', $filters['grade'])->value('name');
        }
        if (filled($filters['section'] ?? null)) {
            $lines[] = 'Section: '.Section::query()->where('public_id', $filters['section'])->value('name');
        }
        if (filled($filters['search'] ?? null)) {
            $lines[] = 'Learner: '.$filters['search'];
        }
        if (filled($filters['status'] ?? null)) {
            $lines[] = 'Status: '.str($filters['status'])->headline();
        }
        if (filled($filters['band'] ?? null)) {
            $lines[] = 'Final grade: '.ReportCatalog::GRADE_BANDS[$filters['band']]['label'];
        } elseif (filled($filters['min_grade'] ?? null) || filled($filters['max_grade'] ?? null)) {
            $lines[] = 'Final grade: '.($filters['min_grade'] ?? '0').' – '.($filters['max_grade'] ?? '100');
        }
        if (filled($filters['date_from'] ?? null) || filled($filters['date_to'] ?? null)) {
            $lines[] = 'Dates: '.($filters['date_from'] ?? '…').' to '.($filters['date_to'] ?? '…');
        }
        if (filled($filters['sort'] ?? null)) {
            $direction = ($filters['direction'] ?? 'asc') === 'desc' ? ' (high to low)' : ' (low to high)';
            $lines[] = 'Sorted by: '.($reports->columns($report, $user)[$filters['sort']] ?? $filters['sort']).$direction;
        }

        return $lines;
    }

    public function export(ReportFilterRequest $request, string $report, RequestReportExportAction $action): RedirectResponse
    {
        $action->execute($report, $request->filters(), $request->user(), $request->pickedColumns());

        return to_route('reporting.index')->with('success', 'Private CSV export queued.');
    }

    public function download(ExportJob $export, Request $request, AuditRecorder $audit): StreamedResponse|RedirectResponse
    {
        if (! Gate::allows('view', $export)) {
            abort(404);
        }
        abort_if($export->expires_at->isPast() || $export->deleted_at !== null, 410, 'This export is no longer available.');
        if ($export->status !== 'completed') {
            return to_route('reporting.index')->withErrors(['export' => 'This export is not ready yet. Refresh the page in a minute.']);
        }
        if (! $this->fileAvailable($export)) {
            return to_route('reporting.index')->withErrors(['export' => 'This export file is no longer on the server. Open the report and request a new CSV export.']);
        }
        $export->increment('download_count');
        $export->update(['last_downloaded_at' => now()]);
        $audit->record($request->user(), 'reporting.export.downloaded', $export, ['report_code' => $export->report_code]);

        return Storage::disk($export->disk)->download($export->storage_path, $export->download_name, ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function fileAvailable(ExportJob $export): bool
    {
        return $export->status === 'completed'
            && $export->deleted_at === null
            && ! $export->expires_at->isPast()
            && $export->storage_path !== null
            && Storage::disk($export->disk)->exists($export->storage_path);
    }

    public function destroy(ExportJob $export, Request $request, DeleteReportExportAction $action): RedirectResponse
    {
        Gate::authorize('delete', $export);
        $action->execute($export, $request->user());

        return back()->with('success', 'Export file deleted. Its audit metadata was retained.');
    }
}
