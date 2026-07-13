<?php

namespace App\Modules\Reporting\Policies;

use App\Models\User;
use App\Modules\Reporting\Models\ExportJob;
use App\Modules\Reporting\Reports\ReportCatalog;

class ExportJobPolicy
{
    public function view(User $user, ExportJob $export): bool
    {
        return $export->requested_by === $user->id && app(ReportCatalog::class)->allows($user, $export->report_code);
    }

    public function delete(User $user, ExportJob $export): bool
    {
        return $this->view($user, $export) && $export->deleted_at === null;
    }
}
