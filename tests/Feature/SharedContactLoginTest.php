<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
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
 * Shared household contacts remain valid communication data in the configured
 * testing environment, but email and mobile are no longer login identifiers.
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

    public function test_a_shared_mobile_number_is_not_a_login_identifier(): void
    {
        $student = $this->account('Shared Student', ['student'], 'StudentPass123');
        $guardian = $this->account('Shared Guardian', ['guardian'], 'GuardianPass123');

        $this->assertGreaterThan($student->id, $guardian->id);
        $this->assertNull(app(AuthenticateUserAction::class)->execute('09760367468', 'StudentPass123'));
        $this->assertNull(app(AuthenticateUserAction::class)->execute('09760367468', 'GuardianPass123'));
    }

    public function test_a_shared_number_is_rejected_over_http(): void
    {
        $this->account('Shared Student', ['student'], 'StudentPass123');
        $guardian = $this->account('Shared Guardian', ['guardian'], 'GuardianPass123');

        $this->post(route('identity.login.store'), [
            'identifier' => '09760367468',
            'password' => 'GuardianPass123',
        ])->assertSessionHasErrors('identifier');

        $this->assertGuest();
        $this->assertNull($guardian->fresh()->last_login_at);
    }

    public function test_a_wrong_password_is_still_rejected_for_every_account_on_the_number(): void
    {
        $this->account('Shared Student', ['student'], 'StudentPass123');
        $this->account('Shared Guardian', ['guardian'], 'GuardianPass123');

        $this->assertNull(app(AuthenticateUserAction::class)->execute('09760367468', 'NeitherPassword1'));
    }

    public function test_unified_authentication_accepts_school_identifiers_not_contacts(): void
    {
        $this->account('Shared Student', ['student'], 'StudentPass123');
        $this->account('Shared Guardian', ['guardian'], 'GuardianPass123');

        $this->assertNull(app(AuthenticateUserAction::class)->execute('09760367468', 'GuardianPass123'));
    }

    public function test_a_disabled_account_on_the_number_does_not_block_an_active_one(): void
    {
        $disabled = $this->account('Disabled First', ['guardian'], 'SamePass12345');
        $disabled->forceFill(['status' => AccountStatus::Disabled])->save();
        $active = $this->account('Active Second', ['guardian'], 'SamePass12345');

        $this->assertNotSame($disabled->id, $active->id);
        $this->assertNull(app(AuthenticateUserAction::class)->execute('09760367468', 'SamePass12345'));
    }

    public function test_email_is_not_a_login_identifier(): void
    {
        $this->account('Shared Student', ['student'], 'StudentPass123');
        $guardian = $this->account('Shared Guardian', ['guardian'], 'GuardianPass123', 'rofelia@example.test');

        $this->assertNull(app(AuthenticateUserAction::class)->execute('rofelia@example.test', 'GuardianPass123'));
        $this->assertNotNull($guardian->email);
    }

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
