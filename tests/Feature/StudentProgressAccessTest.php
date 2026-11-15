<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Communications\Models\Announcement;
use App\Modules\Communications\Models\AnnouncementAudience;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\Enrollment\Models\EnrollmentStatusHistory;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Requirements\Models\DocumentType;
use App\Modules\Shared\Audit\Models\AuditLog;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentProgressAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_connect_an_existing_student_account_and_enable_its_portal(): void
    {
        $admin = $this->user(['admin']);
        $studentAccount = $this->user(['student'], 'Portal Student');
        $student = StudentProfile::query()->create([
            'student_number' => 'STU-001',
            'first_name' => 'Portal',
            'last_name' => 'Student',
        ]);
        $enrollment = $this->approvedEnrollment($student, 7);

        $this->actingAs($studentAccount)
            ->get(route('student.enrollment.progress'))
            ->assertOk()
            ->assertSee('No Registrar-issued application')
            ->assertDontSee(route('enrollment.public.start'), false);

        $this->actingAs($admin)
            ->put(route('people.admin.students.account', $student), [
                'user_public_id' => $studentAccount->public_id,
                'identity_confirmed' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Student account connected. The student portal is now available.');

        $this->assertDatabaseHas('student_profiles', [
            'id' => $student->id,
            'user_id' => $studentAccount->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'people.student.account_linked',
            'subject_id' => $student->id,
        ]);

        $this->actingAs($studentAccount->refresh())
            ->get(route('student.enrollment.progress'))
            ->assertOk()
            ->assertSee('Student portal')
            ->assertSee($enrollment->application_number);
    }

    public function test_student_account_linking_requires_admin_access(): void
    {
        $registrar = $this->user(['registrar']);
        $studentAccount = $this->user(['student'], 'Access Boundary');
        $student = StudentProfile::query()->create(['first_name' => 'Access', 'last_name' => 'Boundary']);

        $this->put(route('people.admin.students.account', $student), [
            'user_public_id' => $studentAccount->public_id,
        ])->assertRedirect(route('identity.login'));

        $this->actingAs($registrar)
            ->put(route('people.admin.students.account', $student), [
                'user_public_id' => $studentAccount->public_id,
            ])
            ->assertForbidden();

        $this->assertNull($student->fresh()->user_id);
    }

    public function test_unlinked_student_sees_submitted_application_timeline_requirements_and_status_link(): void
    {
        $studentAccount = $this->user(['student'], 'Jacob Flores Ramos');
        $otherStudent = $this->user(['student'], 'Other Student');
        $application = $this->studentApplication($studentAccount, 'submitted');
        EnrollmentStatusHistory::query()->create([
            'enrollment_id' => $application->id,
            'from_status' => 'draft',
            'to_status' => 'submitted',
            'changed_by' => $studentAccount->id,
            'changed_at' => now(),
        ]);
        $birthCertificate = DocumentType::query()->create(['code' => 'PORTAL-BIRTH', 'name' => 'Birth Certificate']);
        $reportCard = DocumentType::query()->create(['code' => 'PORTAL-CARD', 'name' => 'School Form 9 (SF9)']);
        EnrollmentRequirement::query()->create([
            'enrollment_id' => $application->id,
            'document_type_id' => $birthCertificate->id,
            'rule_snapshot' => ['is_required' => true, 'is_blocking' => true],
            'status' => 'missing',
        ]);
        EnrollmentRequirement::query()->create([
            'enrollment_id' => $application->id,
            'document_type_id' => $reportCard->id,
            'rule_snapshot' => ['is_required' => true, 'is_blocking' => true],
            'status' => 'submitted',
        ]);

        $this->actingAs($studentAccount)
            ->get(route('student.enrollment.progress'))
            ->assertOk()
            ->assertSee($application->application_number)
            ->assertSee('Grade 8')
            ->assertSee($application->academicYear->name)
            ->assertSee('Submitted')
            ->assertSee('Application timeline')
            ->assertSee('Birth Certificate')
            ->assertSee('Not yet received')
            ->assertSee('School Form 9 (SF9)')
            ->assertSee('This requirement has been recorded by the Registrar.')
            ->assertSee('View application status')
            ->assertDontSee('Start enrollment application')
            ->assertDontSee('Resume draft');

        $this->actingAs($studentAccount)
            ->get(route('enrollment.public.status', $application))
            ->assertOk()
            ->assertSee($application->application_number);

        $this->actingAs($otherStudent)
            ->get(route('enrollment.public.status', $application))
            ->assertForbidden();
    }

    public function test_unlinked_student_can_resume_only_a_draft_owned_by_that_account(): void
    {
        $studentAccount = $this->user(['student'], 'Draft Owner');
        $application = $this->studentApplication($studentAccount, 'draft');

        $this->actingAs($studentAccount)
            ->get(route('student.enrollment.progress'))
            ->assertOk()
            ->assertSee('Resume draft')
            ->assertDontSee('Start enrollment application')
            ->assertDontSee('View application status');

        $this->actingAs($studentAccount)
            ->get(route('enrollment.public.start'))
            ->assertOk()
            ->assertSee('Complete the enrollment application');
    }

    public function test_terminal_student_application_allows_a_new_application(): void
    {
        $studentAccount = $this->user(['student'], 'Closed Application');
        $application = $this->studentApplication($studentAccount, 'cancelled');

        $this->actingAs($studentAccount)
            ->get(route('student.enrollment.progress'))
            ->assertOk()
            ->assertSee($application->application_number)
            ->assertSee('View application status')
            ->assertSee('visit the Registrar for an in-person review', false)
            ->assertDontSee('Resume draft');

        $this->actingAs($studentAccount)
            ->get(route('enrollment.public.start'))
            ->assertRedirect(route('identity.dashboard'))
            ->assertSessionHasErrors('application');
    }

    public function test_linking_rejects_non_student_and_already_linked_accounts(): void
    {
        $admin = $this->user(['admin']);
        $teacher = $this->user(['teacher']);
        $studentAccount = $this->user(['student'], 'First Student');
        $firstStudent = StudentProfile::query()->create(['first_name' => 'First', 'last_name' => 'Student']);
        $secondStudent = StudentProfile::query()->create(['first_name' => 'Second', 'last_name' => 'Student']);
        $this->approvedEnrollment($firstStudent, 7);

        $this->actingAs($admin)
            ->put(route('people.admin.students.account', $firstStudent), [
                'user_public_id' => $teacher->public_id,
                'identity_confirmed' => '1',
            ])
            ->assertSessionHasErrors('user_public_id');

        $this->assertNull($firstStudent->fresh()->user_id);

        $this->actingAs($admin)
            ->put(route('people.admin.students.account', $firstStudent), [
                'user_public_id' => $studentAccount->public_id,
                'identity_confirmed' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->put(route('people.admin.students.account', $secondStudent), [
                'user_public_id' => $studentAccount->public_id,
                'identity_confirmed' => '1',
            ])
            ->assertSessionHasErrors('user_public_id');

        $this->assertNull($secondStudent->fresh()->user_id);
    }

    public function test_student_account_linking_requires_approved_enrollment(): void
    {
        $admin = $this->user(['admin']);
        $studentAccount = $this->user(['student'], 'Draft Student');
        $student = StudentProfile::query()->create(['first_name' => 'Draft', 'last_name' => 'Student']);

        $this->actingAs($admin)
            ->put(route('people.admin.students.account', $student), [
                'user_public_id' => $studentAccount->public_id,
                'identity_confirmed' => '1',
            ])
            ->assertSessionHasErrors('user_public_id');

        $this->assertNull($student->fresh()->user_id);
    }

    public function test_elementary_student_account_requires_an_audited_admin_exception(): void
    {
        $admin = $this->user(['admin']);
        $studentAccount = $this->user(['student'], 'Elementary Student');
        $student = StudentProfile::query()->create(['first_name' => 'Elementary', 'last_name' => 'Student']);
        $enrollment = $this->approvedEnrollment($student, 2);

        $this->actingAs($admin)
            ->put(route('people.admin.students.account', $student), [
                'user_public_id' => $studentAccount->public_id,
                'identity_confirmed' => '1',
            ])
            ->assertSessionHasErrors('exception_reason');

        $this->actingAs($admin)
            ->put(route('people.admin.students.account', $student), [
                'user_public_id' => $studentAccount->public_id,
                'identity_confirmed' => '1',
                'exception_reason' => 'N/A',
            ])
            ->assertSessionHasErrors('exception_reason');

        $reason = 'Approved accessibility exception for supervised student portal use.';
        $this->actingAs($admin)
            ->put(route('people.admin.students.account', $student), [
                'user_public_id' => $studentAccount->public_id,
                'identity_confirmed' => '1',
                'exception_reason' => $reason,
            ])
            ->assertRedirect();

        $audit = AuditLog::query()
            ->where('action', 'people.student.account_linked')
            ->where('subject_id', $student->id)
            ->firstOrFail();

        $this->assertSame($enrollment->public_id, $audit->metadata['enrollment_public_id']);
        $this->assertTrue($audit->metadata['is_elementary_exception']);
        $this->assertSame($reason, $audit->metadata['exception_reason']);
    }

    public function test_admin_cannot_use_the_student_progress_route(): void
    {
        $admin = $this->user(['admin']);
        StudentProfile::query()->create([
            'first_name' => 'Unrelated',
            'last_name' => 'Student',
        ]);

        $this->actingAs($admin)
            ->get(route('student.enrollment.progress'))
            ->assertForbidden();
    }

    public function test_mismatched_student_account_is_rejected_and_existing_bad_data_is_fail_closed(): void
    {
        $admin = $this->user(['admin']);
        $jacobAccount = $this->user(['student'], 'Jacob Ramos');
        $mark = StudentProfile::query()->create(['first_name' => 'Mark', 'last_name' => 'Dela Cruz']);
        $enrollment = $this->approvedEnrollment($mark, 2);

        $this->actingAs($admin)
            ->put(route('people.admin.students.account', $mark), [
                'user_public_id' => $jacobAccount->public_id,
                'identity_confirmed' => '1',
                'exception_reason' => 'Approved supervised portal exception.',
            ])
            ->assertSessionHasErrors('user_public_id');

        $this->assertNull($mark->fresh()->user_id);

        $mark->update(['user_id' => $jacobAccount->id]);
        $this->actingAs($jacobAccount->refresh())
            ->get(route('student.enrollment.progress'))
            ->assertOk()
            ->assertSee('Student account connection needs correction')
            ->assertDontSee($enrollment->application_number);
    }

    public function test_admin_can_disconnect_a_wrong_account_without_deleting_enrollment_history(): void
    {
        $admin = $this->user(['admin']);
        $studentAccount = $this->user(['student'], 'Portal Student');
        $student = StudentProfile::query()->create(['first_name' => 'Portal', 'last_name' => 'Student', 'user_id' => $studentAccount->id]);
        $enrollment = $this->approvedEnrollment($student, 8);

        $this->actingAs($admin)
            ->delete(route('people.admin.students.account.disconnect', $student), [
                'reason' => 'Correcting a verified account-to-profile mismatch.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertNull($student->fresh()->user_id);
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'student_id' => $student->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'people.student.account_disconnected',
            'subject_id' => $student->id,
        ]);
    }

    public function test_manual_profile_and_guardian_creation_forms_are_retired(): void
    {
        // D-048: walk-in access creates learners and guardians; Family links no longer offers blank creation forms.
        $this->assertFalse(Route::has('people.admin.students.store'));
        $this->assertFalse(Route::has('people.admin.guardians.store'));
        $this->assertFalse(Route::has('people.admin.links.store'));
    }

    public function test_admin_can_remove_an_unused_mistaken_profile_and_clear_only_its_pending_duplicate_reference(): void
    {
        $admin = $this->user(['admin']);
        $mistaken = StudentProfile::query()->create([
            'student_number' => 'LEGACY-MISTAKE',
            'first_name' => 'Jacob',
            'last_name' => 'Ramos',
        ]);
        $applicant = StudentProfile::query()->create([
            'first_name' => 'Jacob',
            'middle_name' => 'Flores',
            'last_name' => 'Ramos',
            'birth_date' => '2013-03-03',
        ]);
        $enrollment = $this->approvedEnrollment($applicant, 8);
        $enrollment->update([
            'status' => 'submitted',
            'approved_at' => null,
            'submitted_at' => now(),
            'duplicate_review_status' => 'pending',
            'potential_duplicate_student_id' => $mistaken->id,
        ]);

        $this->actingAs($admin)
            ->get(route('people.admin.index'))
            ->assertOk()
            ->assertSee('Remove this mistaken, unused learner record')
            ->assertSee(route('people.admin.students.destroy', $mistaken), false);

        $this->actingAs($admin)
            ->delete(route('people.admin.students.destroy', $mistaken), [
                'reason' => 'The profile was manually created before Jacob completed the online application.',
                'deletion_confirmed' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('student_profiles', ['id' => $mistaken->id]);
        $this->assertDatabaseHas('student_profiles', ['id' => $applicant->id]);
        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'student_id' => $applicant->id,
            'potential_duplicate_student_id' => null,
            'duplicate_review_status' => 'clear',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'enrollment.duplicate.mistaken_candidate_removed',
            'subject_id' => $enrollment->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'people.student.mistaken_profile_deleted',
            'subject_id' => $mistaken->id,
        ]);
    }

    public function test_mistaken_profile_removal_is_admin_only_and_fails_closed_for_every_protected_history(): void
    {
        $admin = $this->user(['admin']);
        $studentAccount = $this->user(['student']);
        $connected = StudentProfile::query()->create(['user_id' => $studentAccount->id, 'first_name' => 'Connected', 'last_name' => 'Student']);
        $enrolled = StudentProfile::query()->create(['first_name' => 'Enrolled', 'last_name' => 'Student']);
        $this->approvedEnrollment($enrolled, 8);
        $related = StudentProfile::query()->create(['first_name' => 'Related', 'last_name' => 'Student']);
        $guardian = GuardianProfile::query()->create(['first_name' => 'Related', 'last_name' => 'Guardian']);
        GuardianStudent::query()->create([
            'guardian_id' => $guardian->id,
            'student_id' => $related->id,
            'relationship_type' => 'mother',
        ]);
        $targeted = StudentProfile::query()->create(['first_name' => 'Targeted', 'last_name' => 'Student']);
        $historicalDuplicate = StudentProfile::query()->create(['first_name' => 'Historical', 'last_name' => 'Duplicate', 'status' => 'duplicate']);
        $announcement = Announcement::query()->create([
            'title' => 'Targeted notice',
            'body' => 'Protected communication history.',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);
        AnnouncementAudience::query()->create([
            'announcement_id' => $announcement->id,
            'audience_type' => 'student',
            'student_id' => $targeted->id,
        ]);
        $unused = StudentProfile::query()->create(['first_name' => 'Unused', 'last_name' => 'Student']);
        $payload = [
            'reason' => 'Attempting a protected profile removal for testing.',
            'deletion_confirmed' => '1',
        ];

        $this->actingAs($studentAccount)
            ->delete(route('people.admin.students.destroy', $unused), $payload)
            ->assertForbidden();

        foreach ([$connected, $enrolled, $related, $targeted, $historicalDuplicate] as $protected) {
            $this->actingAs($admin)
                ->delete(route('people.admin.students.destroy', $protected), $payload)
                ->assertSessionHasErrors('student');
            $this->assertDatabaseHas('student_profiles', ['id' => $protected->id]);
        }

        $this->actingAs($admin)
            ->delete(route('people.admin.students.destroy', $unused), ['reason' => 'Missing explicit confirmation.'])
            ->assertSessionHasErrors('deletion_confirmed');
        $this->assertDatabaseHas('student_profiles', ['id' => $unused->id]);
    }

    /** @param list<string> $roles */
    private function user(array $roles, ?string $name = null): User
    {
        $user = User::query()->create([
            'name' => $name ?? 'Portal '.Str::random(6),
            'email' => Str::lower(Str::random(12)).'@example.test',
            'password' => Hash::make('Password12345'),
            'status' => AccountStatus::Active,
            'password_changed_at' => now(),
        ]);

        return app(AssignRolesAction::class)->execute($user, $roles, null);
    }

    private function approvedEnrollment(StudentProfile $student, int $gradeSequence): Enrollment
    {
        $suffix = Str::upper(Str::random(8));
        $year = AcademicYear::query()->create([
            'code' => 'PORTAL-'.$suffix,
            'name' => 'Portal Test Year '.$suffix,
            'starts_on' => '2026-06-01',
            'ends_on' => '2027-03-31',
            'status' => 'open',
            'enrollment_is_open' => true,
        ]);
        $grade = GradeLevel::query()->create([
            'code' => 'PORTAL-G'.$gradeSequence.'-'.$suffix,
            'name' => 'Grade '.$gradeSequence,
            'sequence' => $gradeSequence,
            'school_stage' => $gradeSequence >= 7 ? 'Junior High School' : 'Elementary',
            'is_active' => true,
        ]);
        $guardian = GuardianProfile::query()->create([
            'first_name' => 'Portal',
            'last_name' => 'Guardian '.$suffix,
            'email' => Str::lower($suffix).'@example.test',
        ]);

        return Enrollment::query()->create([
            'application_number' => 'PORTAL-'.$suffix,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'grade_level_id' => $grade->id,
            'applicant_guardian_id' => $guardian->id,
            'student_category' => 'regular',
            'status' => 'approved',
            'approved_at' => now(),
            'active_enrollment_key' => $student->id.':'.$year->id,
        ]);
    }

    private function studentApplication(User $account, string $status): Enrollment
    {
        $suffix = Str::upper(Str::random(8));
        $year = AcademicYear::query()->create([
            'code' => 'APPLICATION-'.$suffix,
            'name' => 'School Year 2026-2027 '.$suffix,
            'starts_on' => '2026-06-01',
            'ends_on' => '2027-03-31',
            'status' => 'open',
            'enrollment_is_open' => true,
        ]);
        $grade = GradeLevel::query()->create([
            'code' => 'APPLICATION-G8-'.$suffix,
            'name' => 'Grade 8',
            'sequence' => 8,
            'school_stage' => 'Junior High School',
            'is_active' => true,
        ]);
        $student = StudentProfile::query()->create([
            'first_name' => 'Jacob',
            'middle_name' => 'Flores',
            'last_name' => 'Ramos',
            'birth_date' => '2013-03-03',
        ]);
        $guardian = GuardianProfile::query()->create([
            'first_name' => 'Portal',
            'last_name' => 'Guardian '.$suffix,
            'email' => Str::lower($suffix).'@example.test',
        ]);
        $terminal = in_array($status, Enrollment::TERMINAL_STATUSES, true);

        return Enrollment::query()->create([
            'application_number' => 'APPLICATION-'.$suffix,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'grade_level_id' => $grade->id,
            'applicant_guardian_id' => $guardian->id,
            'student_applicant_user_id' => $account->id,
            'access_issued_to_user_id' => $account->id,
            'walk_in_verified_at' => now(),
            'student_category' => 'regular',
            'guardian_relationship' => 'mother',
            'status' => $status,
            'contact_verified_at' => now(),
            'submitted_at' => $status === 'submitted' ? now() : null,
            'active_enrollment_key' => $terminal ? null : $student->id.':'.$year->id,
            'active_student_application_key' => $terminal ? null : $account->id.':'.$year->id,
        ]);
    }
}
