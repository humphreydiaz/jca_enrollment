<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Enrollment\Actions\TransitionEnrollmentAction;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Actions\AuthenticateUserAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Actions\IssueStaffNumberAction;
use App\Modules\People\Actions\IssueStudentNumberAction;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\StaffProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\People\Support\RandomThreeDigitSuffix;
use App\Modules\Requirements\Models\DocumentType;
use App\Modules\Requirements\Models\RequirementRule;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\CompletesApplicationForms;
use Tests\TestCase;

class StudentNumberTest extends TestCase
{
    use CompletesApplicationForms, RefreshDatabase;

    private static int $gradeSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        self::$gradeSequence = 0;
        app()->instance(RandomThreeDigitSuffix::class, new class extends RandomThreeDigitSuffix
        {
            private int $next = 100;

            public function generate(): string
            {
                return (string) $this->next++;
            }
        });
    }

    public function test_submitting_an_application_issues_a_student_number_in_the_school_format(): void
    {
        $enrollment = $this->draftEnrollment();

        $this->submit($enrollment);

        $number = $enrollment->student->fresh()->student_number;
        $this->assertSame('JCA-26-100', $number);
    }

    public function test_numbers_use_the_entry_year_and_a_three_digit_random_suffix(): void
    {
        $year2026 = $this->year('2026-2027', '2026-06-01', '2027-03-31');
        $year2027 = $this->year('2027-2028', '2027-06-01', '2028-03-31');

        $first = $this->draftEnrollment($year2026);
        $second = $this->draftEnrollment($year2026);
        $otherYear = $this->draftEnrollment($year2027);

        $this->submit($first);
        $this->submit($second);
        $this->submit($otherYear);

        $this->assertSame('JCA-26-100', $first->student->fresh()->student_number);
        $this->assertSame('JCA-26-101', $second->student->fresh()->student_number);
        $this->assertSame('JCA-27-102', $otherYear->student->fresh()->student_number);
    }

    public function test_a_number_is_never_reused_after_the_application_is_cancelled(): void
    {
        $first = $this->draftEnrollment();
        $this->submit($first);
        $this->assertSame('JCA-26-100', $first->student->fresh()->student_number);

        // Cancel the first application, then submit a fresh one.
        app(TransitionEnrollmentAction::class)->execute($first->fresh(), 'cancelled', null, 'Withdrawn by family');

        $second = $this->draftEnrollment();
        $this->submit($second);

        $this->assertSame('JCA-26-101', $second->student->fresh()->student_number);
    }

    public function test_issuance_retries_when_the_random_candidate_already_exists(): void
    {
        // Seed / re-imported data: a profile already holds a number for the year
        // but the counter table has no row for it. Issuance must clear the gap.
        StudentProfile::query()->create([
            'first_name' => 'Seed',
            'last_name' => 'Data',
            'student_number' => 'JCA-26-100',
        ]);

        $enrollment = $this->draftEnrollment();
        $this->submit($enrollment);

        $this->assertSame('JCA-26-101', $enrollment->student->fresh()->student_number);
    }

    public function test_the_number_does_not_change_on_a_later_transition(): void
    {
        $enrollment = $this->draftEnrollment();
        $enrollment->update(['duplicate_review_status' => 'clear']);
        $this->submit($enrollment);
        $issued = $enrollment->student->fresh()->student_number;

        app(TransitionEnrollmentAction::class)->execute($enrollment->fresh(), 'under_review', $this->staff('registrar'));

        $this->assertSame($issued, $enrollment->student->fresh()->student_number);
    }

    public function test_an_already_numbered_learner_is_not_issued_a_second_number(): void
    {
        $student = StudentProfile::query()->create(['first_name' => 'Prom', 'last_name' => 'Oted', 'student_number' => 'JCA-25-107']);

        $number = app(IssueStudentNumberAction::class)->execute($student, $this->year());

        $this->assertSame('JCA-25-107', $number);
        $this->assertSame('JCA-25-107', $student->fresh()->student_number);
    }

    public function test_a_student_can_sign_in_with_their_number_before_approval(): void
    {
        $account = $this->studentAccount('LearnPass123');
        $enrollment = $this->draftEnrollment(applicantUser: $account);
        $this->submit($enrollment);
        $number = $enrollment->student->fresh()->student_number;

        // Pre-approval: the profile is not yet connected to the login, so the
        // number resolves through the application that issued it.
        $this->assertNull($enrollment->student->fresh()->user_id);

        $resolved = app(AuthenticateUserAction::class)->execute($number, 'LearnPass123');
        $this->assertNotNull($resolved);
        $this->assertSame($account->id, $resolved->id);
    }

    public function test_a_connected_student_signs_in_through_the_profile_account(): void
    {
        $account = $this->studentAccount('LearnPass123');
        $enrollment = $this->draftEnrollment();
        $this->submit($enrollment);
        $student = $enrollment->student->fresh();
        $student->update(['user_id' => $account->id]);

        $resolved = app(AuthenticateUserAction::class)->execute($student->student_number, 'LearnPass123');
        $this->assertNotNull($resolved);
        $this->assertSame($account->id, $resolved->id);
    }

    public function test_a_verified_guardian_uses_the_childs_student_id_with_their_own_password(): void
    {
        $studentAccount = $this->studentAccount('StudentPass123');
        $guardianAccount = User::query()->create([
            'name' => 'Guardian Account',
            'password' => Hash::make('GuardianPass123'),
            'status' => AccountStatus::Active,
            'password_changed_at' => now(),
        ]);
        $guardianAccount = app(AssignRolesAction::class)->execute($guardianAccount, ['guardian'], null);
        $student = StudentProfile::query()->create([
            'user_id' => $studentAccount->id,
            'first_name' => 'Linked',
            'last_name' => 'Learner',
            'student_number' => 'JCA-26-555',
        ]);
        $guardian = GuardianProfile::query()->create([
            'user_id' => $guardianAccount->id,
            'first_name' => 'Linked',
            'last_name' => 'Guardian',
        ]);
        GuardianStudent::query()->create([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'relationship_type' => 'Parent',
            'may_manage_enrollment' => true,
            'verified_at' => now(),
            'effective_from' => today(),
        ]);

        $this->assertSame(
            $studentAccount->id,
            app(AuthenticateUserAction::class)->execute('JCA-26-555', 'StudentPass123')?->id,
        );
        $this->assertSame(
            $guardianAccount->id,
            app(AuthenticateUserAction::class)->execute('JCA-26-555', 'GuardianPass123')?->id,
        );
    }

    public function test_a_student_id_fails_closed_when_two_linked_accounts_share_a_password(): void
    {
        $studentAccount = $this->studentAccount('SharedPass123');
        $guardianAccount = User::query()->create([
            'name' => 'Guardian Account',
            'password' => Hash::make('SharedPass123'),
            'status' => AccountStatus::Active,
            'password_changed_at' => now(),
        ]);
        $guardianAccount = app(AssignRolesAction::class)->execute($guardianAccount, ['guardian'], null);
        $student = StudentProfile::query()->create([
            'user_id' => $studentAccount->id,
            'first_name' => 'Shared',
            'last_name' => 'Password',
            'student_number' => 'JCA-26-556',
        ]);
        $guardian = GuardianProfile::query()->create([
            'user_id' => $guardianAccount->id,
            'first_name' => 'Shared',
            'last_name' => 'Guardian',
        ]);
        GuardianStudent::query()->create([
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'relationship_type' => 'Parent',
            'may_manage_enrollment' => true,
            'verified_at' => now(),
            'effective_from' => today(),
        ]);

        $this->assertNull(app(AuthenticateUserAction::class)->execute('JCA-26-556', 'SharedPass123'));
    }

    public function test_student_number_login_works_end_to_end_over_http(): void
    {
        $account = $this->studentAccount('LearnPass123');
        $enrollment = $this->draftEnrollment(applicantUser: $account);
        $this->submit($enrollment);
        $number = $enrollment->student->fresh()->student_number;

        $this->post(route('identity.login.store'), ['identifier' => $number, 'password' => 'LearnPass123'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($account);
    }

    public function test_wrong_password_unknown_number_and_email_identifier_are_rejected(): void
    {
        $account = $this->studentAccount('LearnPass123', 'known@example.test');
        $enrollment = $this->draftEnrollment(applicantUser: $account);
        $this->submit($enrollment);
        $number = $enrollment->student->fresh()->student_number;

        $this->assertNull(app(AuthenticateUserAction::class)->execute($number, 'WrongPass999'));
        $this->assertNull(app(AuthenticateUserAction::class)->execute('JCA-26-999', 'LearnPass123'));

        $this->assertNull(app(AuthenticateUserAction::class)->execute('known@example.test', 'LearnPass123'));
    }

    public function test_backfill_issues_numbers_to_submitted_learners_and_skips_drafts_and_terminals(): void
    {
        $submitted = $this->draftEnrollment();
        $submitted->update(['status' => 'submitted', 'submitted_at' => now()]);

        $draftOnly = $this->draftEnrollment();

        $cancelled = $this->draftEnrollment();
        $cancelled->update(['status' => 'cancelled', 'submitted_at' => now()]);

        $this->artisan('students:backfill-numbers')->assertSuccessful();

        $this->assertSame('JCA-26-100', $submitted->student->fresh()->student_number);
        $this->assertNull($draftOnly->student->fresh()->student_number);
        $this->assertNull($cancelled->student->fresh()->student_number);
    }

    public function test_staff_number_uses_employment_year_and_retries_a_collision(): void
    {
        $existingUser = User::factory()->create();
        StaffProfile::query()->create([
            'user_id' => $existingUser->id,
            'employee_number' => '26-JCA-100',
            'active_from' => '2026-01-15',
        ]);
        $newUser = User::factory()->create();
        $staff = StaffProfile::query()->create(['user_id' => $newUser->id]);

        $number = app(IssueStaffNumberAction::class)->execute($staff, now()->setDate(2026, 6, 1));

        $this->assertSame('26-JCA-101', $number);
        $this->assertDatabaseHas('staff_profiles', [
            'id' => $staff->id,
            'employee_number' => '26-JCA-101',
            'active_from' => '2026-06-01',
        ]);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function draftEnrollment(?AcademicYear $year = null, ?User $applicantUser = null): Enrollment
    {
        $year ??= $this->year();
        $grade = $this->grade();
        $student = StudentProfile::query()->create([
            'first_name' => 'Learner',
            'last_name' => Str::random(6),
            'birth_date' => '2013-04-05',
        ]);

        return Enrollment::query()->create([
            'application_number' => 'JCA-2026-'.Str::upper(Str::random(8)),
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'grade_level_id' => $grade->id,
            'student_applicant_user_id' => $applicantUser?->id,
            'student_category' => 'regular',
            'guardian_relationship' => 'mother',
            'status' => 'draft',
            'contact_verified_at' => now(),
            'duplicate_review_status' => 'clear',
            'active_enrollment_key' => $student->id.':'.$year->id,
        ]);
    }

    private function submit(Enrollment $enrollment): void
    {
        $this->completeApplicationForm($enrollment);
        app(TransitionEnrollmentAction::class)->execute($enrollment, 'submitted', null);
    }

    private function year(string $prefix = '2026-2027', string $starts = '2026-06-01', string $ends = '2027-03-31'): AcademicYear
    {
        $year = AcademicYear::query()->create([
            'code' => $prefix.'-'.Str::random(4),
            'name' => 'School Year '.$prefix,
            'starts_on' => $starts,
            'ends_on' => $ends,
            'status' => 'open',
        ]);

        $document = DocumentType::query()->firstOrCreate(
            ['code' => 'SN-BIRTH'],
            ['name' => 'Student number test birth certificate'],
        );
        RequirementRule::query()->create([
            'academic_year_id' => $year->id,
            'student_category' => 'regular',
            'document_type_id' => $document->id,
            'is_required' => true,
            'is_blocking' => true,
        ]);

        return $year;
    }

    private function grade(): GradeLevel
    {
        $sequence = ++self::$gradeSequence;

        return GradeLevel::query()->create([
            'code' => 'SN-G'.$sequence.'-'.Str::random(4),
            'name' => 'Grade '.$sequence,
            'sequence' => $sequence,
            'school_stage' => 'Junior High School',
            'is_active' => true,
        ]);
    }

    private function studentAccount(string $password, ?string $email = null): User
    {
        $user = User::query()->create([
            'name' => 'Learner '.Str::random(5),
            'email' => $email ?? Str::lower(Str::random(10)).'@example.test',
            'password' => Hash::make($password),
            'status' => AccountStatus::Active,
            'password_changed_at' => now(),
        ]);

        return app(AssignRolesAction::class)->execute($user, ['student'], null);
    }

    private function staff(string $role): User
    {
        $user = User::query()->create([
            'name' => 'Staff '.Str::random(5),
            'email' => Str::lower(Str::random(10)).'@example.test',
            'password' => Hash::make('StaffPass123'),
            'status' => AccountStatus::Active,
            'password_changed_at' => now(),
        ]);

        return app(AssignRolesAction::class)->execute($user, [$role], null);
    }
}
