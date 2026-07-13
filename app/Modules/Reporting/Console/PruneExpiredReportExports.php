<?php

namespace App\Modules\Reporting\Console;

use App\Modules\Reporting\Models\ExportJob;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneExpiredReportExports extends Command
{
    protected $signature = 'reports:prune-exports {--limit=500 : Maximum exports processed in one run}';

    protected $description = 'Remove expired private report files while preserving audited export history';

    public function handle(AuditRecorder $audit): int
    {
        $limit = max(1, min((int) $this->option('limit'), 5000));
        $exports = ExportJob::query()
            ->whereNull('deleted_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($exports as $export) {
            if ($export->storage_path !== null) {
                Storage::disk($export->disk)->delete($export->storage_path);
            }

            $export->update([
                'status' => 'expired',
                'storage_path' => null,
                'deleted_at' => now(),
                'deleted_by' => null,
            ]);
            $audit->record(null, 'reporting.export.expired', $export, ['report_code' => $export->report_code]);
        }

        $this->info('Expired report exports pruned: '.$exports->count());

        return self::SUCCESS;
    }
}
