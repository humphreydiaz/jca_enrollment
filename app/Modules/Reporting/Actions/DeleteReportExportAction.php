<?php

namespace App\Modules\Reporting\Actions;

use App\Models\User;
use App\Modules\Reporting\Models\ExportJob;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\Storage;

class DeleteReportExportAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(ExportJob $export, User $actor): void
    {
        if ($export->storage_path !== null) {
            Storage::disk($export->disk)->delete($export->storage_path);
        }
        $export->update(['status' => 'deleted', 'storage_path' => null, 'deleted_at' => now(), 'deleted_by' => $actor->id]);
        $this->audit->record($actor, 'reporting.export.deleted', $export, ['report_code' => $export->report_code]);
    }
}
