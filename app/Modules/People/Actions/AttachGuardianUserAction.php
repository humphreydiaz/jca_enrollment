<?php

namespace App\Modules\People\Actions;

use App\Models\User;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\Shared\Audit\AuditRecorder;

class AttachGuardianUserAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(GuardianProfile $guardian, User $user, User $actor): GuardianProfile
    {
        $guardian->forceFill(['user_id' => $user->id])->save();
        $this->audit->record($actor, 'people.guardian.account_attached', $guardian);

        return $guardian->refresh();
    }
}
