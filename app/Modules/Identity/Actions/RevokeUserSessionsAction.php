<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RevokeUserSessionsAction
{
    public function execute(User $user): void
    {
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->delete();
    }
}
