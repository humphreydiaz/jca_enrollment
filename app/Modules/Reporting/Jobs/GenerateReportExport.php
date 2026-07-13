<?php

namespace App\Modules\Reporting\Jobs;

use App\Modules\Reporting\Models\ExportJob;
use App\Modules\Reporting\Reports\ReportCatalog;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GenerateReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $exportId) {}

    public function handle(ReportCatalog $reports, AuditRecorder $audit): void
    {
        $export = ExportJob::query()->with('requester')->findOrFail($this->exportId);
        if ($export->status === 'completed' || $export->deleted_at !== null) {
            return;
        }
        if ($export->expires_at->isPast()) {
            $export->update(['status' => 'expired', 'deleted_at' => now()]);
            $audit->record(null, 'reporting.export.expired', $export, ['report_code' => $export->report_code]);

            return;
        }
        if (! $reports->allows($export->requester, $export->report_code)) {
            $export->update(['status' => 'failed', 'failure_code' => 'authorization_revoked']);

            return;
        }
        $export->update(['status' => 'generating', 'failure_code' => null]);

        try {
            $limit = (int) config('reporting.export_row_limit');
            $rows = $reports->query($export->report_code, $export->requester, $export->filter_snapshot ?? [])->limit($limit + 1)->get();
            if ($rows->count() > $limit) {
                $export->update(['status' => 'failed', 'failure_code' => 'row_limit_exceeded']);

                return;
            }
            $columns = $reports->columns($export->report_code, $export->requester);
            $stream = fopen('php://temp', 'w+b');
            if ($stream === false) {
                throw new \RuntimeException('Temporary export stream unavailable.');
            }
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, array_values($columns));
            foreach ($rows as $row) {
                fputcsv($stream, array_map(fn (string $field): string => $this->safeCsvValue(data_get($row, $field)), array_keys($columns)));
            }
            rewind($stream);
            $contents = stream_get_contents($stream);
            fclose($stream);
            if ($contents === false) {
                throw new \RuntimeException('Export stream could not be read.');
            }
            $path = now()->format('Y/m').'/'.Str::ulid().'.csv';
            if (! Storage::disk($export->disk)->put($path, $contents)) {
                throw new \RuntimeException('Private export storage failed.');
            }
            $export->update(['status' => 'completed', 'storage_path' => $path, 'row_count' => $rows->count(), 'checksum_sha256' => hash('sha256', $contents), 'generated_at' => now()]);
            $audit->record($export->requester, 'reporting.export.generated', $export, ['report_code' => $export->report_code, 'row_count' => $rows->count()]);
        } catch (Throwable) {
            $export->update(['status' => 'failed', 'failure_code' => 'generation_failed']);
        }
    }

    private function safeCsvValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        $string = $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : (string) $value;

        return preg_match('/^[=+\-@]/u', ltrim($string)) ? "'".$string : $string;
    }
}
