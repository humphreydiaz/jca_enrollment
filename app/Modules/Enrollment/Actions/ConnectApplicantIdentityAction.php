<?php

namespace App\Modules\Enrollment\Actions;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\ContactNormalizer;
use App\Modules\Identity\Actions\CreateAccountInvitationAction;
use App\Modules\Identity\Actions\CreatedInvitation;
use App\Modules\Identity\Actions\CreateInvitedUserAction;
use App\Modules\People\Actions\LinkGuardianStudentAction;
use App\Modules\People\Actions\VerifyGuardianStudentLinkAction;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConnectApplicantIdentityAction
{
    public function __construct(
        private readonly CreateInvitedUserAction $createUser,
        private readonly CreateAccountInvitationAction $createInvitation,
        private readonly LinkGuardianStudentAction $linkGuardian,
        private readonly VerifyGuardianStudentLinkAction $verifyLink,
        private readonly AuditRecorder $audit,
    ) {}

    public function execute(Enrollment $enrollment, User $actor): ?CreatedInvitation
    {
        return DB::transaction(function () use ($enrollment, $actor): ?CreatedInvitation {
            $record = Enrollment::query()->lockForUpdate()->with(['guardian', 'student'])->findOrFail($enrollment->id);
            if ($record->contact_verified_at === null || $record->duplicate_review_status === 'pending') {
                throw ValidationException::withMessages(['application' => 'Verify the contact and resolve the duplicate review before connecting an account.']);
            }

            $guardian = $record->guardian;
            $email = ContactNormalizer::email($guardian->email);
            $mobile = ContactNormalizer::mobile($guardian->mobile);
            $channel = $email !== null ? 'email' : 'mobile';
            $sentTo = $email ?? $mobile;
            if ($sentTo === null) {
                throw ValidationException::withMessages(['application' => 'The applicant has no verified contact channel.']);
            }

            $user = User::query()->where($channel, $sentTo)->first();
            $invitation = null;
            if ($user === null) {
                $created = $this->createUser->execute([
                    'name' => $guardian->full_name,
                    'email' => $email,
                    'mobile' => $mobile,
                ], ['guardian'], $channel, $sentTo, $actor);
                $user = $created->user;
                $invitation = $created->invitation;
            } else {
                if (! $user->hasRole('guardian')) {
                    throw ValidationException::withMessages(['application' => 'The verified contact belongs to an incompatible account. Admin review is required.']);
                }
                if (! $user->isActive()) {
                    $invitation = $this->createInvitation->execute($user, $channel, $sentTo, $actor);
                }
            }

            if ($guardian->user_id !== null && $guardian->user_id !== $user->id) {
                throw ValidationException::withMessages(['application' => 'The guardian profile is already connected to another account.']);
            }
            if ($user->guardianProfile !== null && $user->guardianProfile->id !== $guardian->id) {
                throw ValidationException::withMessages(['application' => 'The verified account already has a different guardian profile. Admin duplicate review is required.']);
            }
            $guardian->update(['user_id' => $user->id, 'email' => $email, 'mobile' => $mobile]);
            $link = $this->linkGuardian->execute($guardian, $record->student, [
                'relationship_type' => $record->guardian_relationship ?: 'Guardian',
                'is_primary_contact' => true,
                'may_manage_enrollment' => true,
            ], $actor);
            $this->verifyLink->execute($link, $actor);
            $this->audit->record($actor, 'enrollment.identity.connected', $record);

            return $invitation;
        });
    }
}
