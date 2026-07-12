<?php

namespace App\Modules\Communications\Policies;

use App\Models\User;
use App\Modules\Communications\Models\Announcement;
use App\Modules\Communications\Queries\VisibleAnnouncementsQuery;

class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'registrar', 'accounting', 'teacher', 'guardian', 'student']);
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return app(VisibleAnnouncementsQuery::class)->forUser($user)->whereKey($announcement->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function retrySms(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
