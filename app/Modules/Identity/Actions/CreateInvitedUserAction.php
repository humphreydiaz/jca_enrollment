<?php

namespace App\Modules\Identity\Actions;

use App\Models\User;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Actions\IssueStaffNumberAction;
use App\Modules\People\Models\StaffProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateInvitedUserAction
{
    public function __construct(
        private readonly AssignRolesAction $assignRoles,
        private readonly CreateAccountInvitationAction $createInvitation,
        private readonly IssueStaffNumberAction $issueStaffNumber,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    /**
     * @param  array{name: string, email?: string|null, mobile?: string|null, employed_on?: string|null}  $attributes
     * @param  list<string>  $roleCodes
     */
    public function execute(array $attributes, array $roleCodes, string $channel, string $sentTo, User $actor): CreatedUserInvitation
    {
        $allowedRoles = ['admin', 'registrar', 'accounting', 'teacher'];

        if (
            count($roleCodes) !== 1 ||
            ! in_array($roleCodes[0], $allowedRoles, true)
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'roles' => 'Create Invited Account is exclusively for staff accounts.',
            ]);
        }

        if (empty($attributes['employed_on'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'employed_on' => 'Employment start date is required.',
            ]);
        }

        return DB::transaction(function () use ($actor, $attributes, $channel, $roleCodes, $sentTo): CreatedUserInvitation {
            $user = User::query()->create([
                'name' => $attributes['name'],
                'email' => ContactNormalizer::email($attributes['email'] ?? null),
                'mobile' => ContactNormalizer::mobile($attributes['mobile'] ?? null),
                'status' => AccountStatus::Invited,
            ]);

            $user = $this->assignRoles->execute($user, $roleCodes, $actor);

            if ($user->roles->contains('is_staff', true)) {
                $employedOn = Carbon::parse($attributes['employed_on']);
                $staff = StaffProfile::query()->firstOrCreate(
                    ['user_id' => $user->id],
                    ['active_from' => $employedOn->toDateString()],
                );
                $this->issueStaffNumber->execute($staff, $employedOn, $actor);
            }

            $createdInvitation = $this->createInvitation->execute($user, $channel, $sentTo, $actor);
            $this->auditRecorder->record($actor, 'identity.user.created', $user, [
                'role_count' => count($roleCodes),
            ]);

            return new CreatedUserInvitation($user, $createdInvitation);
        });
    }
}
