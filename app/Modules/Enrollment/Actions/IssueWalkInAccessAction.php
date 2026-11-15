<?php

namespace App\Modules\Enrollment\Actions;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\Requirements\Actions\EnsureOfficialRequirementRulesAction;
use App\Modules\Requirements\Actions\GenerateEnrollmentChecklistAction;
use App\Modules\Requirements\Actions\RecordHardCopyReceiptAction;
use App\Modules\Identity\Actions\ContactNormalizer;
use App\Modules\Identity\Actions\CreateTemporaryAccessUserAction;
use App\Modules\People\Actions\AttachGuardianUserAction;
use App\Modules\People\Actions\AttachStudentUserAction;
use App\Modules\People\Actions\CompleteGuardianDetailsAction;
use App\Modules\People\Actions\CreateGuardianProfileAction;
use App\Modules\People\Actions\CreateStudentProfileAction;
use App\Modules\People\Actions\IssueStudentNumberAction;
use App\Modules\People\Actions\LinkGuardianStudentAction;
use App\Modules\People\Actions\VerifyGuardianStudentLinkAction;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IssueWalkInAccessAction
{
    public function __construct(
        private readonly CreateTemporaryAccessUserAction $createTemporaryAccess,
        private readonly CreateStudentProfileAction $createStudent,
        private readonly CreateGuardianProfileAction $createGuardian,
        private readonly AttachGuardianUserAction $attachGuardianUser,
        private readonly AttachStudentUserAction $attachStudentUser,
        private readonly IssueStudentNumberAction $issueStudentNumber,
        private readonly LinkGuardianStudentAction $linkGuardian,
        private readonly VerifyGuardianStudentLinkAction $verifyLink,
        private readonly AuditRecorder $audit,
        private readonly EnsureOfficialRequirementRulesAction $ensureRequirementRules,
        private readonly GenerateEnrollmentChecklistAction $generateChecklist,
        private readonly RecordHardCopyReceiptAction $recordHardCopy,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(array $data, User $actor): IssuedWalkInAccess
    {
        return DB::transaction(function () use ($actor, $data): IssuedWalkInAccess {
            $year = AcademicYear::query()->lockForUpdate()->findOrFail($data['academic_year_id']);
            $grade = GradeLevel::query()->lockForUpdate()->findOrFail($data['grade_level_id']);
            $ownerIsStudent = $grade->allowsStudentAccountByDefault();
            // D-047: guardian details are recorded at the counter only when the guardian owns the
            // account (Nursery–Grade 6); contact details are optional there.
            $email = $ownerIsStudent ? null : ContactNormalizer::email($data['guardian_email'] ?? null);
            $mobile = $ownerIsStudent ? null : ContactNormalizer::mobile($data['guardian_mobile'] ?? null);

            $this->rejectPossibleDuplicates($data, $email, $mobile, $year);

            $student = $this->createStudent->execute([
                'lrn' => filled($data['lrn'] ?? null) ? trim($data['lrn']) : null,
                'first_name' => trim($data['student_first_name']),
                'middle_name' => filled($data['student_middle_name'] ?? null) ? trim($data['student_middle_name']) : null,
                'last_name' => trim($data['student_last_name']),
                'extension_name' => filled($data['extension_name'] ?? null) ? trim($data['extension_name']) : null,
                'sex' => $data['sex'],
                'birth_date' => $data['birth_date'],
                'religion' => 'Christianity',
            ], $actor);
            $studentNumber = $this->issueStudentNumber->execute($student, $year);

            // Grades 7–10 (learner-owned): the guardian starts as a placeholder that the learner
            // completes on the application. Nursery–Grade 6: the adult at the counter owns the account.
            $guardian = $this->createGuardian->execute($ownerIsStudent ? [
                'first_name' => CompleteGuardianDetailsAction::PENDING_FIRST_NAME,
                'last_name' => $student->last_name,
            ] : [
                'first_name' => trim($data['guardian_first_name']),
                'middle_name' => filled($data['guardian_middle_name'] ?? null) ? trim($data['guardian_middle_name']) : null,
                'last_name' => trim($data['guardian_last_name']),
                'extension_name' => filled($data['guardian_extension_name'] ?? null) ? trim($data['guardian_extension_name']) : null,
                'mobile' => $mobile,
                'email' => $email,
            ], $actor);

            $ownerName = $ownerIsStudent ? $student->full_name : $guardian->full_name;
            $createdAccess = $this->createTemporaryAccess->execute(
                $ownerName,
                $email,
                $mobile,
                $ownerIsStudent ? 'student' : 'guardian',
                $actor,
            );
            $user = $createdAccess->user;

            if (! $ownerIsStudent) {
                $guardian = $this->attachGuardianUser->execute($guardian, $user, $actor);
            } else {
                // D-048: the learner's own login is connected to their record right away, so
                // grades, finance, and schedule pages work for them after enrollment.
                $this->attachStudentUser->execute($student, $user, $actor, 'walk-in access');
            }

            $link = $this->linkGuardian->execute($guardian, $student, [
                'relationship_type' => $ownerIsStudent ? 'other' : $data['guardian_relationship'], // learner-owned: replaced from the application
                'is_primary_contact' => true,
                'may_receive_sms' => false,
                'may_manage_enrollment' => true,
                'may_view_financials' => true, // D-053: the parent who enrolls the learner sees what is due and paid
            ], $actor);
            $this->verifyLink->execute($link, $actor);

            $enrollment = Enrollment::query()->create([
                'application_number' => 'JCA-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'grade_level_id' => $grade->id,
                'applicant_guardian_id' => $guardian->id,
                'student_applicant_user_id' => $ownerIsStudent ? $user->id : null,
                'access_issued_to_user_id' => $user->id,
                'walk_in_verified_by' => $actor->id,
                'walk_in_verified_at' => now(),
                'access_issued_at' => now(),
                'student_category' => $data['student_category'],
                'guardian_relationship' => $ownerIsStudent ? null : $data['guardian_relationship'], // null = completed on the application
                'status' => 'draft',
                'active_enrollment_key' => $student->id.':'.$year->id,
                'active_student_application_key' => $ownerIsStudent ? $user->id.':'.$year->id : null,
                'duplicate_review_status' => 'clear',
            ]);

            // Generate the official checklist.
            $this->ensureRequirementRules->execute($year, $actor);
            $this->generateChecklist->execute($enrollment);

            $requirements = EnrollmentRequirement::query()
                ->where('enrollment_id', $enrollment->id)
                ->get();

            if ($requirements->isEmpty()) {
                throw ValidationException::withMessages([
                    'student_category' =>
                        'No requirements are configured for this application type.',
                ]);
            }

            $receivedIds = collect(
                $data['received_document_types'] ?? []
            )
                ->map(fn ($id) => (int) $id)
                ->unique();

            $allowedIds = $requirements
                ->pluck('document_type_id')
                ->map(fn ($id) => (int) $id);

            if ($receivedIds->diff($allowedIds)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'received_document_types' =>
                        'Invalid requirements for this application type.',
                ]);
            }

            foreach ($requirements as $requirement) {
                if ($receivedIds->contains(
                    (int) $requirement->document_type_id
                )) {
                    $this->recordHardCopy->execute(
                        $requirement,
                        true,
                        $actor,
                        'Hard copy received during walk-in issuance.'
                    );
                }
            }

            $this->audit->record($actor, 'enrollment.walk_in_access.issued', $enrollment, [
                'account_owner_type' => $ownerIsStudent ? 'student' : 'guardian',
                'temporary_access_expires_at' => $createdAccess->expiresAt->toIso8601String(),
            ]);

            return new IssuedWalkInAccess(
                $enrollment,
                $studentNumber,
                $createdAccess->temporaryPassword,
                $ownerName,
                $ownerIsStudent ? 'Student' : 'Parent/Guardian',
                $createdAccess->expiresAt,
            );
        });
    }

    /** @param array<string, mixed> $data */
    private function rejectPossibleDuplicates(array $data, ?string $email, ?string $mobile, AcademicYear $year): void
    {
        $studentQuery = StudentProfile::query()
            ->whereDate('birth_date', $data['birth_date'])
            ->where('first_name', trim($data['student_first_name']))
            ->where('last_name', trim($data['student_last_name']));

        if (filled($data['lrn'] ?? null)) {
            $studentQuery->orWhere('lrn', trim($data['lrn']));
        }

        $possibleStudent = $studentQuery->lockForUpdate()->first();
        if ($possibleStudent !== null) {
            $hasActiveApplication = Enrollment::query()
                ->where('student_id', $possibleStudent->id)
                ->where('academic_year_id', $year->id)
                ->whereNotIn('status', Enrollment::TERMINAL_STATUSES)
                ->exists();

            throw ValidationException::withMessages([
                'student_first_name' => $hasActiveApplication
                    ? 'This learner already has an active application for the selected school year.'
                    : 'A possible existing learner record was found. Review and connect that record before issuing access; do not create a duplicate.',
            ]);
        }

        if ($email === null && $mobile === null) {
            return;
        }
        $contactMatches = fn ($query) => $query->where(fn ($match) => $match
            ->when($email !== null, fn ($q) => $q->orWhere('email', $email))
            ->when($mobile !== null, fn ($q) => $q->orWhere('mobile', $mobile)));
        if (GuardianProfile::query()->where($contactMatches)->lockForUpdate()->exists()
            || User::query()->where($contactMatches)->lockForUpdate()->exists()) {
            throw ValidationException::withMessages([
                'guardian_mobile' => 'This mobile number or email already belongs to another parent/guardian or account. Leave the contact details blank for now; the family can add them on the application, where the Registrar reviews possible duplicates.',
            ]);
        }
    }
}
