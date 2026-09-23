<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Enrollment\Actions\ConnectApplicantIdentityAction;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Actions\AuthenticateUserAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * With shared contact accounts enabled, a household may reuse one mobile number
 * across a parent and a child, so a single identifier can match several
 * accounts. The password has to decide which of them is signing in — resolving
 * to whichever row comes first locks everyone else out.
 *
 * Reproducing that needs two users on one number, which the schema's
 * users_mobile_unique index normally prevents, so these tests drop the index for
 * their own duration and clean up the rows they insert.
 */
class SharedContactLoginTest extends TestCase
{
    use RefreshDatabase;

    private const SHARED_MOBILE = '+639760367468';

    /** @var list<int> */
    private array $created = [];

    /** @var list<int> */
    private array $createdEnrollments = [];

    /** @var list<int> */
    private array $createdGuardians = [];

    /** @var list<int> */
    private array $createdStudents = [];

    /** @var list<int> */
    private array $createdYears = [];

    /** @var list<int> */
    private array $createdGrades = [];

    private Migration $sharedMobileMigration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        config()->set('enrollment.allow_shared_contact_accounts', true);
        $this->sharedMobileMigration = require database_path('migrations/2026_09_19_000022_allow_shared_user_mobile_when_testing_configured.php');
        $this->sharedMobileMigration->up();
    }

    protected function tearDown(): void
    {
        // The DDL above commits the surrounding transaction, so undo by hand.
        DB::table('account_invitations')->whereIn('user_id', $this->created)->delete();
        DB::table('guardian_student')
            ->whereIn('guardian_id', $this->createdGuardians)
            ->orWhereIn('student_id', $this->createdStudents)
            ->delete();
        DB::table('enrollments')->whereIn('id', $this->createdEnrollments)->delete();
        DB::table('guardian_profiles')->whereIn('id', $this->createdGuardians)->delete();
        DB::table('student_profiles')->whereIn('id', $this->createdStudents)->delete();
        DB::table('user_roles')->whereIn('user_id', $this->created)->delete();
        DB::table('users')->whereIn('id', $this->created)->delete();
        DB::table('grade_levels')->whereIn('id', $this->createdGrades)->delete();
        DB::table('academic_years')->whereIn('id', $this->createdYears)->delete();
        $this->sharedMobileMigration->down();

        parent::tearDown();
    }

    public function test_each_account_sharing_a_mobile_number_signs_in_with_its_own_password(): void
    {
        $student = $this->account('Shared Student', ['student'], 'StudentPass123');
        $guardian = $this->account('Shared Guardian', ['guardian'], 'GuardianPass123');

        // The guardian is not the first row for this number, which is what used
        // to make the lookup resolve to the student and reject her password.
        $this->assertGreaterThan($student->id, $guardian->id);

        $this->assertSame(
            $student->id,
            app(AuthenticateUserAction::class)->execute('09760367468', 'StudentPass123')?->id,
        );
        $this->assertSame(
            $guardian->id,
            app(AuthenticateUserAction::class)->execute('09760367468', 'GuardianPass123')?->id,
        );
    }

    public function test_a_shared_number_signs_in_over_http_for_the_second_account(): void
    {
        $this->account('Shared Student', ['student'], 'StudentPass123');
        $guardian = $this->account('Shared Guardian', ['guardian'], 'GuardianPass123');

        $this->post(route('identity.login.store'), [
            'identifier' => '09760367468',
            'password' => 'GuardianPass123',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($guardian);
    }

    public function test_a_wrong_password_is_still_rejected_for_every_account_on_the_number(): void
    {
        $this->account('Shared Student', ['student'], 'StudentPass123');
        $this->account('Shared Guardian', ['guardian'], 'GuardianPass123');

        $this->assertNull(app(AuthenticateUserAction::class)->execute('09760367468', 'NeitherPassword1'));
    }

    public function test_the_role_gate_still_applies_when_a_number_is_shared(): void
    {
        $this->account('Shared Student', ['student'], 'StudentPass123');
        $this->account('Shared Guardian', ['guardian'], 'GuardianPass123');

        $this->assertNull(app(AuthenticateUserAction::class)->execute('09760367468', 'GuardianPass123', ['admin', 'registrar']));
        $this->assertNotNull(app(AuthenticateUserAction::class)->execute('09760367468', 'GuardianPass123', ['guardian']));
    }

    public function test_a_disabled_account_on_the_number_does_not_block_an_active_one(): void
    {
        $disabled = $this->account('Disabled First', ['guardian'], 'SamePass12345');
        $disabled->forceFill(['status' => AccountStatus::Disabled])->save();
        $active = $this->account('Active Second', ['guardian'], 'SamePass12345');

        $this->assertSame(
            $active->id,
            app(AuthenticateUserAction::class)->execute('09760367468', 'SamePass12345')?->id,
        );
    }

    public function test_signing_in_by_email_still_resolves_the_right_account(): void
    {
        $this->account('Shared Student', ['student'], 'StudentPass123');
        $guardian = $this->account('Shared Guardian', ['guardian'], 'GuardianPass123', 'rofelia@example.test');

        $this->assertSame(
            $guardian->id,
            app(AuthenticateUserAction::class)->execute('rofelia@example.test', 'GuardianPass123')?->id,
        );
    }

    public function test_two_unrelated_student_parent_pairs_can_share_one_testing_mobile(): void
    {
        $gradeSevenStudent = $this->account('Grade 7 Student', ['student'], 'Grade7StudentPass123');
        $gradeEightStudent = $this->account('Grade 8 Student', ['student'], 'Grade8StudentPass123');
        $registrar = $this->account('Testing Registrar', ['registrar'], 'RegistrarPass123', 'registrar@example.test', '+639171111111');

        $year = AcademicYear::query()->create([
            'code' => 'SHARED-2026',
            'name' => 'Shared Contact Test Year',
            'starts_on' => '2026-06-01',
            'ends_on' => '2027-03-31',
            'status' => 'open',
        ]);
        $this->createdYears[] = $year->id;

        $gradeSeven = GradeLevel::query()->create([
            'code' => 'SHARED-G7',
            'name' => 'Shared Contact Grade 7',
            'sequence' => 7,
            'school_stage' => 'Junior High School',
            'is_active' => true,
        ]);
        $this->createdGrades[] = $gradeSeven->id;

        $gradeEight = GradeLevel::query()->create([
            'code' => 'SHARED-G8',
            'name' => 'Shared Contact Grade 8',
            'sequence' => 8,
            'school_stage' => 'Junior High School',
            'is_active' => true,
        ]);
        $this->createdGrades[] = $gradeEight->id;

        $pairs = [
            [$gradeSevenStudent, $gradeSeven, 'Grade 7', 'Parent One', 'SHARED-CONTACT-007'],
            [$gradeEightStudent, $gradeEight, 'Grade 8', 'Parent Two', 'SHARED-CONTACT-008'],
        ];

        foreach ($pairs as [$studentAccount, $grade, $studentName, $parentName, $applicationNumber]) {
            [$parentFirstName, $parentLastName] = explode(' ', $parentName, 2);
            $guardian = GuardianProfile::query()->create([
                'first_name' => $parentFirstName,
                'last_name' => $parentLastName,
                'mobile' => self::SHARED_MOBILE,
            ]);
            $this->createdGuardians[] = $guardian->id;

            $student = StudentProfile::query()->create([
                'user_id' => $studentAccount->id,
                'first_name' => $studentName,
                'last_name' => 'Student',
                'birth_date' => '2012-01-02',
            ]);
            $this->createdStudents[] = $student->id;

            $enrollment = Enrollment::query()->create([
                'application_number' => $applicationNumber,
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'grade_level_id' => $grade->id,
                'applicant_guardian_id' => $guardian->id,
                'student_category' => 'regular',
                'guardian_relationship' => 'Parent',
                'status' => 'submitted',
                'active_enrollment_key' => $student->id.':'.$year->id,
                'contact_verified_at' => now(),
                'duplicate_review_status' => 'clear',
            ]);
            $this->createdEnrollments[] = $enrollment->id;

            $created = app(ConnectApplicantIdentityAction::class)->execute($enrollment, $registrar);
            $guardianUser = $guardian->fresh()->user;
            $this->created[] = $guardianUser->id;

            $this->assertNotNull($created);
            $this->assertNotSame($studentAccount->id, $guardianUser->id);
            $this->assertSame(self::SHARED_MOBILE, $guardianUser->mobile);
            $this->assertTrue($guardianUser->hasRole('guardian'));
            $this->assertDatabaseHas('guardian_student', [
                'guardian_id' => $guardian->id,
                'student_id' => $student->id,
            ]);
        }

        $this->assertSame(4, User::query()->where('mobile', self::SHARED_MOBILE)->count());
    }

    /**
     * @param  list<string>  $roles
     */
    private function account(string $name, array $roles, string $password, ?string $email = null, string $mobile = self::SHARED_MOBILE): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'mobile' => $mobile,
            'password' => Hash::make($password),
            'status' => AccountStatus::Active,
            'password_changed_at' => now(),
        ]);
        $this->created[] = $user->id;

        return app(AssignRolesAction::class)->execute($user, $roles, null);
    }
}
