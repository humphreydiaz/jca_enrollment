<?php

namespace App\Modules\People\Actions;

use App\Models\User;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

class VerifyGuardianStudentLinkAction
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function execute(GuardianStudent $link, User $actor): void
    {
        DB::transaction(function () use ($actor, $link): void {
            $lockedLink = GuardianStudent::query()->lockForUpdate()->findOrFail($link->id);
            $lockedLink->update([
                'verified_by' => $actor->id,
                'verified_at' => now(),
            ]);

            $this->auditRecorder->record($actor, 'people.guardian_student.verified', $lockedLink->student, [
                'guardian_public_id' => $lockedLink->guardian->public_id,
            ]);
        });
    }
}
