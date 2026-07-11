<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\StaffProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

class CreateInvitedUserAction
{
    public function __construct(
        private readonly AssignRolesAction $assignRoles,
        private readonly CreateAccountInvitationAction $createInvitation,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    /**
     * @param  array{name: string, email?: string|null, mobile?: string|null}  $attributes
     * @param  list<string>  $roleCodes
     */
    public function execute(array $attributes, array $roleCodes, string $channel, string $sentTo, User $actor): CreatedUserInvitation
    {
        return DB::transaction(function () use ($actor, $attributes, $channel, $roleCodes, $sentTo): CreatedUserInvitation {
            $user = User::query()->create([
                'name' => $attributes['name'],
                'email' => ContactNormalizer::email($attributes['email'] ?? null),
                'mobile' => ContactNormalizer::mobile($attributes['mobile'] ?? null),
                'status' => AccountStatus::Invited,
            ]);

            $user = $this->assignRoles->execute($user, $roleCodes, $actor);

            if ($user->roles->contains('is_staff', true)) {
                StaffProfile::query()->firstOrCreate(['user_id' => $user->id]);
            }

            $createdInvitation = $this->createInvitation->execute($user, $channel, $sentTo, $actor);
            $this->auditRecorder->record($actor, 'identity.user.created', $user, [
                'role_count' => count($roleCodes),
            ]);

            return new CreatedUserInvitation($user, $createdInvitation);
        });
    }
}
