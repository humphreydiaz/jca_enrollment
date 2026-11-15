<?php

namespace App\Modules\Enrollment\Actions;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\ReplaceTemporaryAccessPasswordAction;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReissueWalkInAccessAction
{
    public function __construct(
        private readonly ReplaceTemporaryAccessPasswordAction $replaceTemporaryAccess,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(Enrollment $enrollment, User $actor): IssuedWalkInAccess
    {
        return DB::transaction(function () use ($actor, $enrollment): IssuedWalkInAccess {
            $record = Enrollment::query()->lockForUpdate()->with(['accessOwner.roles', 'student'])->findOrFail($enrollment->id);
            $owner = $record->accessOwner;

            if ($record->status !== 'draft' || $owner === null) {
                throw ValidationException::withMessages([
                    'application' => 'Temporary access can be replaced only for an uncompleted Registrar-issued draft. Use the audited recovery workflow after the owner establishes a private password.',
                ]);
            }

            $replacement = $this->replaceTemporaryAccess->execute($owner, $actor);
            $this->audit->record($actor, 'enrollment.walk_in_access.reissued', $record, [
                'temporary_access_expires_at' => $replacement->expiresAt->toIso8601String(),
            ]);

            return new IssuedWalkInAccess(
                $record,
                $record->student->student_number,
                $replacement->temporaryPassword,
                $owner->name,
                $owner->hasRole('student') ? 'Student' : 'Parent/Guardian',
                $replacement->expiresAt,
            );
        });
    }
}
