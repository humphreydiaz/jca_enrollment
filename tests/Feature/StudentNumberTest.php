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
use App\Modules\People\Actions\IssueStudentNumberAction;
use App\Modules\People\Models\StudentProfile;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentNumberTest extends TestCase
{
    use RefreshDatabase;

    private static int $gradeSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        self::$gradeSequence = 0;
    }

    public function test_submitting_an_application_issues_a_student_number_in_the_school_format(): void
    {
        $enrollment = $this->draftEnrollment();

        $this->submit($enrollment);

        $number = $enrollment->student->fresh()->student_number;
        $this->assertSame('S-2026-2027-0001', $number);
    }

    public function test_numbers_are_sequential_within_a_year_and_independent_across_years(): void
    {
        $year2026 = $this->year('2026-2027', '2026-06-01', '2027-03-31');
        $year2027 = $this->year('2027-2028', '2027-06-01', '2028-03-31');

        $first = $this->draftEnrollment($year2026);
        $second = $this->draftEnrollment($year2026);
        $otherYear = $this->draftEnrollment($year2027);

        $this->submit($first);
        $this->submit($second);
        $this->submit($otherYear);

        $this->assertSame('S-2026-2027-0001', $first->student->fresh()->student_number);
        $this->assertSame('S-2026-2027-0002', $second->student->fresh()->student_number);
        $this->assertSame('S-2027-2028-0001', $otherYear->student->fresh()->student_number);
    }

    public function test_a_number_is_never_reused_after_the_application_is_cancelled(): void
    {
        $first = $this->draftEnrollment();
        $this->submit($first);
        $this->assertSame('S-2026-2027-0001', $first->student->fresh()->student_number);

        // Cancel the first application, then submit a fresh one.
        app(TransitionEnrollmentAction::class)->execute($first->fresh(), 'cancelled', null, 'Withdrawn by family');

        $second = $this->draftEnrollment();
        $this->submit($second);

        // The cancelled 0001 is retired; the next learner gets 0002, not 0001.
        $this->assertSame('S-2026-2027-0002', $second->student->fresh()->student_number);
    }

    public function test_issuance_skips_past_numbers_already_present_without_a_counter_row(): void
    {
        // Seed / re-imported data: a profile already holds a number for the year
        // but the counter table has no row for it. Issuance must clear the gap.
        StudentProfile::query()->create([
            'first_name' => 'Seed',
            'last_name' => 'Data',
            'student_number' => 'S-2026-2027-0041',
        ]);

        $enrollment = $this->draftEnrollment();
        $this->submit($enrollment);

        $this->assertSame('S-2026-2027-0042', $enrollment->student->fresh()->student_number);
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
        $student = StudentProfile::query()->create(['first_name' => 'Prom', 'last_name' => 'Oted', 'student_number' => 'S-2025-2026-0007']);

        $number = app(IssueStudentNumberAction::class)->execute($student, $this->year());

        $this->assertSame('S-2025-2026-0007', $number);
        $this->assertSame('S-2025-2026-0007', $student->fresh()->student_number);
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

        $resolved = app(AuthenticateUserAction::class)->execute($number, 'LearnPass123', ['student']);
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

        $resolved = app(AuthenticateUserAction::class)->execute($student->student_number, 'LearnPass123', ['student']);
        $this->assertNotNull($resolved);
        $this->assertSame($account->id, $resolved->id);
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

    public function test_a_wrong_password_or_unknown_number_is_rejected_and_email_login_still_works(): void
    {
        $account = $this->studentAccount('LearnPass123', 'known@example.test');
        $enrollment = $this->draftEnrollment(applicantUser: $account);
        $this->submit($enrollment);
        $number = $enrollment->student->fresh()->student_number;

        $this->assertNull(app(AuthenticateUserAction::class)->execute($number, 'WrongPass999', ['student']));
        $this->assertNull(app(AuthenticateUserAction::class)->execute('S-2026-2027-9999', 'LearnPass123', ['student']));

        // The pre-existing email path is untouched.
        $viaEmail = app(AuthenticateUserAction::class)->execute('known@example.test', 'LearnPass123', ['student']);
        $this->assertNotNull($viaEmail);
        $this->assertSame($account->id, $viaEmail->id);
    }

    public function test_backfill_issues_numbers_to_submitted_learners_and_skips_drafts_and_terminals(): void
    {
        $submitted = $this->draftEnrollment();
        $submitted->update(['status' => 'submitted', 'submitted_at' => now()]);

        $draftOnly = $this->draftEnrollment();

        $cancelled = $this->draftEnrollment();
        $cancelled->update(['status' => 'cancelled', 'submitted_at' => now()]);

        $this->artisan('students:backfill-numbers')->assertSuccessful();

        $this->assertSame('S-2026-2027-0001', $submitted->student->fresh()->student_number);
        $this->assertNull($draftOnly->student->fresh()->student_number);
        $this->assertNull($cancelled->student->fresh()->student_number);
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
        app(TransitionEnrollmentAction::class)->execute($enrollment, 'submitted', null);
    }

    private function year(string $prefix = '2026-2027', string $starts = '2026-06-01', string $ends = '2027-03-31'): AcademicYear
    {
        return AcademicYear::query()->create([
            'code' => $prefix.'-'.Str::random(4),
            'name' => 'School Year '.$prefix,
            'starts_on' => $starts,
            'ends_on' => $ends,
            'status' => 'open',
        ]);
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
