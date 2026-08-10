<?php

namespace App\Modules\People\Actions;

use App\Models\User;
use App\Modules\Identity\Actions\RevokeUserSessionsAction;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DisconnectStudentAccountAction
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly RevokeUserSessionsAction $revokeUserSessions,
    ) {}

    public function execute(StudentProfile $student, User $actor, string $reason): StudentProfile
    {
        return DB::transaction(function () use ($actor, $reason, $student): StudentProfile {
            $lockedStudent = StudentProfile::query()->lockForUpdate()->findOrFail($student->id);

            if ($lockedStudent->user_id === null) {
                throw ValidationException::withMessages([
                    'account' => 'This student profile does not have a connected account.',
                ]);
            }

            $account = User::query()->lockForUpdate()->findOrFail($lockedStudent->user_id);
            $lockedStudent->update(['user_id' => null]);
            $this->revokeUserSessions->execute($account);
            $this->auditRecorder->record($actor, 'people.student.account_disconnected', $lockedStudent, [
                'disconnected_user_public_id' => $account->public_id,
                'reason' => $reason,
            ]);

            return $lockedStudent->fresh();
        });
    }
}
