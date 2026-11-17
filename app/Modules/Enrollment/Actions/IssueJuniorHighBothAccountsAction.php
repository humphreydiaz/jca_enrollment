<?php

namespace App\Modules\Enrollment\Actions;

use App\Models\User;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Identity\Actions\ContactNormalizer;
use App\Modules\Identity\Actions\CreateTemporaryAccessUserAction;
use App\Modules\People\Actions\LinkGuardianStudentAction;
use App\Modules\People\Actions\VerifyGuardianStudentLinkAction;
use App\Modules\People\Models\GuardianStudent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssueJuniorHighBothAccountsAction
{
    public function __construct(
        private readonly IssueWalkInAccessAction $issueStudent,
        private readonly CreateTemporaryAccessUserAction $createParent,
        private readonly LinkGuardianStudentAction $linkGuardian,
        private readonly VerifyGuardianStudentLinkAction $verifyLink,
    ) {}

    public function execute(array $data, User $actor): array
    {
        abort_unless(
            $actor->hasAnyRole(['admin', 'registrar']),
            403
        );

        $grade = GradeLevel::query()
            ->find($data['grade_level_id'] ?? null);

        if (! in_array(
            $grade?->code,
            ['G7', 'G8', 'G9', 'G10'],
            true
        )) {
            throw ValidationException::withMessages([
                'grade_level_id' =>
                    'Both-account issuance is for Grade 7-10 only.',
            ]);
        }

        if (! filter_var(
            $data['identity_confirmed'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        )) {
            throw ValidationException::withMessages([
                'identity_confirmed' =>
                    'Verify both the student and parent first.',
            ]);
        }

        $relationship = trim(
            (string) ($data['guardian_relationship'] ?? '')
        );

        if ($relationship === '') {
            throw ValidationException::withMessages([
                'guardian_relationship' =>
                    'Parent relationship is required.',
            ]);
        }

        $mobile = ContactNormalizer::mobile(
            $data['guardian_mobile'] ?? null
        );

        if (! $mobile) {
            throw ValidationException::withMessages([
                'guardian_mobile' =>
                    'A valid parent mobile number is required.',
            ]);
        }

        return DB::transaction(function () use (
            $data,
            $actor,
            $relationship,
            $mobile
        ): array {
            // Create the student's existing walk-in account.
            $issued = $this->issueStudent->execute(
                $data,
                $actor
            );

            $enrollment = $issued->enrollment->fresh([
                'student',
                'guardian',
            ]);

            $student = $enrollment->student;
            $guardian = $enrollment->guardian;

            if (! $student || ! $student->user_id) {
                throw ValidationException::withMessages([
                    'student' =>
                        'Student Account could not be created.',
                ]);
            }

            if (! $guardian) {
                throw ValidationException::withMessages([
                    'guardian' =>
                        'Parent profile could not be created.',
                ]);
            }

            // Update the Parent/Guardian profile.
            $guardian->fill([
                'first_name' => trim(
                    $data['guardian_first_name']
                ),
                'middle_name' => filled(
                    $data['guardian_middle_name'] ?? null
                )
                    ? trim($data['guardian_middle_name'])
                    : null,
                'last_name' => trim(
                    $data['guardian_last_name']
                ),
                'extension_name' => filled(
                    $data['guardian_extension_name'] ?? null
                )
                    ? trim($data['guardian_extension_name'])
                    : null,
                'mobile' => $mobile,
                'email' => ContactNormalizer::email(
                    $data['guardian_email'] ?? null
                ),
            ]);

            $guardian->save();

            $parentUser = $guardian->user_id
                ? User::query()->find($guardian->user_id)
                : null;

            if ($guardian->user_id && ! $parentUser) {
                throw ValidationException::withMessages([
                    'guardian' =>
                        'Existing Parent Account was not found.',
                ]);
            }

            if (
                $parentUser
                && ! $parentUser->hasRole('guardian')
            ) {
                throw ValidationException::withMessages([
                    'guardian' =>
                        'The account is not a Parent Account.',
                ]);
            }

            if (
                $parentUser
                && $parentUser->id == $student->user_id
            ) {
                throw ValidationException::withMessages([
                    'guardian' =>
                        'Student and Parent must have separate accounts.',
                ]);
            }

            $parentPassword = null;
            $parentExpiresAt = null;
            $parentStatus = 'Existing Parent Account linked';

            if (! $parentUser) {
                $existingAccount = User::query()
                    ->where('mobile', $mobile)
                    ->first();

                if ($existingAccount) {
                    throw ValidationException::withMessages([
                        'guardian_mobile' =>
                            'This mobile number already has an account. Verify the existing Parent Account first.',
                    ]);
                }

                $parentId = $this->generateParentId();

                // Create an active Parent Account with a
                // temporary password, not an invitation.
                $created = $this->createParent->execute(
                    $guardian->full_name,
                    $guardian->email,
                    $mobile,
                    'guardian',
                    $actor
                );

                $parentUser = $created->user;

                if (! $parentUser) {
                    throw ValidationException::withMessages([
                        'guardian' =>
                            'Parent Account creation failed.',
                    ]);
                }

                // The PAR-YY-NNN sign-in ID lives on the guardian profile, which is
                // what StudentNumberLoginResolver reads. users.public_id stays the
                // opaque ULID used in URLs.
                $guardian->forceFill([
                    'user_id' => $parentUser->id,
                    'parent_login_id' => $parentId,
                ])->save();

                $parentPassword = $created->temporaryPassword;
                $parentExpiresAt = $created->expiresAt;
                $parentStatus = 'Active - Temporary access issued';
            }

            // Connect Parent and Student.
            $link = GuardianStudent::query()
                ->where('guardian_id', $guardian->id)
                ->where('student_id', $student->id)
                ->whereNull('ended_at')
                ->first();

            if (! $link) {
                $link = $this->linkGuardian->execute(
                    $guardian,
                    $student,
                    [
                        'relationship_type' => $relationship,
                        'is_primary_contact' => true,
                        'may_receive_sms' => true,
                        'may_manage_enrollment' => false,
                        'may_view_financials' => true,
                    ],
                    $actor
                );
            } else {
                $link->forceFill([
                    'relationship_type' => $relationship,
                    'may_manage_enrollment' => false,
                ])->save();
            }

            // Verify the family relationship.
            if (! $link->verified_at) {
                $this->verifyLink->execute(
                    $link,
                    $actor
                );
            }

            return [
                'issued' => $issued,
                'student' => $student,
                'guardian' => $guardian,
                'parent_user' => $parentUser,
                'parent_login' => $guardian->parent_login_id,
                'parent_temporary_password' => $parentPassword,
                'parent_expires_at' => $parentExpiresAt,
                'parent_status' => $parentStatus,
                'relationship_verified' =>
                    $link->fresh()->verified_at !== null,
            ];
        });
    }

    private function generateParentId(): string
    {
        $year = now('Asia/Manila')->format('y');

        // PAR-YY-001 to PAR-YY-999 (000 is never issued, matching the
        // 2026_11_02 backfill). Lock the year's rows so two Registrar desks
        // cannot issue the same ID at once; the unique index is the backstop.
        $used = DB::table('guardian_profiles')
            ->where('parent_login_id', 'like', "PAR-{$year}-%")
            ->lockForUpdate()
            ->pluck('parent_login_id')
            ->flip();

        for ($attempt = 1; $attempt <= 999; $attempt++) {
            $parentId = sprintf('PAR-%s-%03d', $year, $attempt);

            if (! $used->has($parentId)) {
                return $parentId;
            }
        }

        throw ValidationException::withMessages([
            'guardian' =>
                'All Parent IDs for this year are already assigned.',
        ]);
    }
}
