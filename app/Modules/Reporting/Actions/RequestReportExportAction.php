<?php

namespace App\Modules\Reporting\Actions;

use App\Models\User;
use App\Modules\Reporting\Jobs\GenerateReportExport;
use App\Modules\Reporting\Models\ExportJob;
use App\Modules\Reporting\Reports\ReportCatalog;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

class RequestReportExportAction
{
    public function __construct(private readonly ReportCatalog $reports, private readonly AuditRecorder $audit) {}

    /** @param array<string, mixed> $filters */
    public function execute(string $reportCode, array $filters, User $actor): ExportJob
    {
        return DB::transaction(function () use ($reportCode, $filters, $actor): ExportJob {
            $export = ExportJob::query()->create([
                'requested_by' => $actor->id,
                'report_code' => $reportCode,
                'filter_snapshot' => $filters ?: null,
                'field_snapshot' => array_keys($this->reports->columns($reportCode, $actor)),
                'status' => 'queued',
                'disk' => (string) config('reporting.disk'),
                'download_name' => $reportCode.'-report-'.now()->format('Ymd-His').'.csv',
                'expires_at' => now()->addHours((int) config('reporting.export_ttl_hours')),
            ]);
            $this->audit->record($actor, 'reporting.export.requested', $export, ['report_code' => $reportCode]);
            GenerateReportExport::dispatch($export->id)->afterCommit();

            return $export;
        });
    }
}
