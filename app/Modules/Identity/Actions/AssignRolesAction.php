<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\UserRole;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignRolesAction
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly RevokeUserSessionsAction $revokeUserSessions,
    ) {}

    /**
     * @param list<string> $roleCodes
     */
    public function execute(User $user, array $roleCodes, ?User $actor): User
    {
        $roleCodes = array_values(array_unique($roleCodes));

        $this->ensureCompatibleRoles($roleCodes);

        return DB::transaction(function () use ($actor, $roleCodes, $user): User {
            $lockedUser = User::query()
                ->lockForUpdate()
                ->findOrFail($user->id);

            $roles = Role::query()
                ->whereIn('code', $roleCodes)
                ->where('is_active', true)
                ->get()
                ->keyBy('code');

            if (count($roleCodes) !== $roles->count()) {
                throw ValidationException::withMessages([
                    'roles' => 'Select only active system roles.',
                ]);
            }

            $activeAssignments = UserRole::query()
                ->where('user_id', $lockedUser->id)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->with('role')
                ->get();

            $activeCodes = $activeAssignments
                ->pluck('role.code')
                ->all();

            foreach (
                $activeAssignments->whereNotIn('role.code', $roleCodes)
                as $assignment
            ) {
                $assignment->update([
                    'active_assignment_key' => null,
                    'revoked_by' => $actor?->id,
                    'revoked_at' => now(),
                ]);
            }

            foreach ($roles->whereNotIn('code', $activeCodes) as $role) {
                UserRole::query()->create([
                    'user_id' => $lockedUser->id,
                    'role_id' => $role->id,
                    'active_assignment_key' => "{$lockedUser->id}:{$role->id}",
                    'assigned_by' => $actor?->id,
                    'assigned_at' => now(),
                ]);
            }

            if ($activeCodes !== $roleCodes) {
                $this->revokeUserSessions->execute($lockedUser);

                $this->auditRecorder->record(
                    $actor,
                    'identity.roles.updated',
                    $lockedUser,
                    [
                        'role_count' => count($roleCodes),
                    ]
                );
            }

            return $lockedUser->fresh('roles');
        });
    }

    /**
     * @param list<string> $roleCodes
     */
    private function ensureCompatibleRoles(array $roleCodes): void
    {
        if (count($roleCodes) !== 1) {
            throw ValidationException::withMessages([
                'roles' => 'Only one role can be assigned to each account. Student and Guardian accounts must be separate.',
            ]);
        }
    }
}