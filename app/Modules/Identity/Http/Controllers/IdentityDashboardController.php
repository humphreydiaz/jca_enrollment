<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Communications\Http\Controllers\PortalDashboardController;
use App\Modules\Communications\Queries\VisibleAnnouncementsQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IdentityDashboardController
{
    public function __invoke(Request $request, PortalDashboardController $dashboard): View
    {
        return $dashboard($request, app(VisibleAnnouncementsQuery::class));
    }
}
