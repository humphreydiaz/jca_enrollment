<?php

namespace App\Modules\Enrollment\Actions;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\ContactNormalizer;
use App\Modules\Identity\Actions\CreateAccountInvitationAction;
use App\Modules\Identity\Actions\CreatedInvitation;
use App\Modules\Identity\Actions\CreateInvitedUserAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\AccountInvitation;
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

            $sharedMobileEnabled = config('enrollment.allow_shared_contact_accounts', false);

            if ($sharedMobileEnabled) {
                // Testing mode permits unrelated Student and Guardian accounts
                // to reuse the same physical phone. A mobile-only application
                // therefore creates its own Guardian login instead of adopting
                // the first Guardian found on that number. A guardian profile
                // that was explicitly linked during duplicate review still
                // reuses its existing account. Email remains unique.
                $user = $guardian->user_id !== null
                    ? User::query()->findOrFail($guardian->user_id)
                    : ($channel === 'email'
                        ? User::query()->where('email', $sentTo)->first()
                        : null);
            } else {
                // Production mode checks every supplied contact before inserting
                // so a secondary collision cannot become a database exception or
                // an implicit merge of two identities.
                $contactOwners = collect([
                    'email' => $email !== null ? User::query()->where('email', $email)->first() : null,
                    'mobile' => $mobile !== null ? User::query()->where('mobile', $mobile)->first() : null,
                ])->filter();
                $user = $contactOwners->get($channel);
                $otherOwner = $contactOwners->first(
                    fn (User $owner): bool => $user === null || $owner->id !== $user->id,
                );

                if ($otherOwner !== null) {
                    $conflictingChannel = $contactOwners->search(
                        fn (User $owner): bool => $owner->id === $otherOwner->id,
                    );
                    $contactLabel = $conflictingChannel === 'email' ? 'email address' : 'mobile number';
                    $conflictRoles = $otherOwner->roles->pluck('code')->implode(', ');

                    throw ValidationException::withMessages([
                        'application' => "This guardian's {$contactLabel} is already registered to another account"
                            .($conflictRoles !== '' ? " (role: {$conflictRoles})" : '')
                            .'. The supplied email and mobile cannot be combined automatically. Give the guardian contact details that no other account uses, then connect again. If these records genuinely belong to the same person, an Admin must reconcile the accounts first.',
                    ]);
                }
            }

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
                    $contactLabel = $channel === 'email' ? 'email address' : 'mobile number';
                    $conflictRoles = $user->roles->pluck('code')->implode(', ');
                    throw ValidationException::withMessages([
                        'application' => "This guardian's {$contactLabel} is already registered to a non-guardian account"
                            .($conflictRoles !== '' ? " (role: {$conflictRoles})" : '')
                            .", so it cannot be used to create a guardian login. Give the guardian a {$contactLabel} that no other account uses, then connect again. If this is genuinely the same person, an Admin must reconcile the two accounts first.",
                    ]);
                }

                if ($user->disabled_at !== null || $user->status === AccountStatus::Disabled) {
                    throw ValidationException::withMessages([
                        'application' => 'The connected guardian account is disabled. An Admin must reactivate it from User management before issuing a new setup link.',
                    ]);
                }

                if ($user->locked_at !== null) {
                    throw ValidationException::withMessages([
                        'application' => 'The connected guardian account is locked and requires Admin security review.',
                    ]);
                }

                if ($user->status === AccountStatus::Invited) {
                    $recentInvitationExists = AccountInvitation::query()
                        ->where('user_id', $user->id)
                        ->where('purpose', 'account_setup')
                        ->whereNull('consumed_at')
                        ->whereNull('revoked_at')
                        ->where('expires_at', '>', now())
                        ->where('created_at', '>', now()->subMinutes(3))
                        ->exists();

                    if ($recentInvitationExists) {
                        throw ValidationException::withMessages([
                            'application' => 'A setup code was issued less than 3 minutes ago. Wait for normal mobile-network delivery before replacing it.',
                        ]);
                    }

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
                'may_view_financials' => true, // D-053: the parent who enrolls the learner sees what is due and paid
            ], $actor);
            $this->verifyLink->execute($link, $actor);
            $this->audit->record($actor, 'enrollment.identity.connected', $record);

            return $invitation;
        });
    }
}
