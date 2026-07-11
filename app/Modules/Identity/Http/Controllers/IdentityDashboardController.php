<?php

namespace App\Modules\Identity\Http\Controllers;

use Illuminate\View\View;

class IdentityDashboardController
{
    public function __invoke(): View
    {
        return view('identity.dashboard');
    }
}
