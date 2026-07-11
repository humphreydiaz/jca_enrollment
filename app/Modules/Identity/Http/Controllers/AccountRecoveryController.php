<?php

namespace App\Modules\Identity\Http\Controllers;

use Illuminate\View\View;

class AccountRecoveryController
{
    public function create(): View
    {
        return view('identity.auth.recovery');
    }
}
