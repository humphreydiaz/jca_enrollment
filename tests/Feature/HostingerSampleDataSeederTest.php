<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use Database\Seeders\Hostinger\HostingerSampleDataSeeder;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HostingerSampleDataSeederTest extends TestCase
{
    use RefreshDatabase;

    private const TEMPORARY_PASSWORD = 'A-Temporary-Password-123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $admin = User::query()->create([
            'name' => 'Existing Administrator',
            'email' => 'existing.admin@example.com',
            'password' => 'Existing-Admin-Password-123',
            'status' => AccountStatus::Active,
            'password_changed_at' => now(),
        ]);
        app(AssignRolesAction::class)->execute($admin, ['admin'], null);
    }

    public function test_it_loads_the_requested_accounts_and_synthetic_school_dataset_safely(): void
    {
        $this->runSeeder();

        $registrar = User::query()->where('email', 'jenny@gmail.com')->sole();
        $accounting = User::query()->where('email', 'angelica@gmail.com')->sole();

        $this->assertSame('Jenny Santos', $registrar->name);
        $this->assertTrue($registrar->hasRole('registrar'));
        $this->assertTrue($registrar->must_change_password);
        $this->assertTrue(Hash::check(self::TEMPORARY_PASSWORD, $registrar->password));

        $this->assertSame('Angelica Garcia', $accounting->name);
        $this->assertTrue($accounting->hasRole('accounting'));
        $this->assertTrue($accounting->must_change_password);
        $this->assertTrue(Hash::check(self::TEMPORARY_PASSWORD, $accounting->password));

        $this->assertSame(6, User::query()->whereHas('roles', fn ($query) => $query->where('code', 'teacher'))->count());
        $this->assertSame(6, DB::table('teaching_assignments')->count());
        $this->assertSame(50, StudentProfile::query()->where('student_number', 'like', 'SIM-2026-%')->count());
        $this->assertSame(48, GuardianProfile::query()->where('email', 'like', 'family%@example.com')->count());
        $this->assertSame(50, DB::table('enrollments')->where('application_number', 'like', 'SIM-2026-%')->count());
        $this->assertSame(50, DB::table('section_assignments')->count());
        $this->assertSame(4, DB::table('document_types')->count());
        $this->assertSame(7, DB::table('requirement_rules')->count());

        $this->assertSame(0, GuardianProfile::query()->whereNotNull('mobile')->count());
        $this->assertSame(0, DB::table('guardian_student')->where('may_receive_sms', true)->count());
    }

    public function test_financial_configuration_is_reviewable_but_not_activated(): void
    {
        $this->runSeeder();

        $this->assertSame(6, FeeSchedule::query()->where('status', 'draft')->count());
        $this->assertSame(0, FeeSchedule::query()->where('status', 'published')->count());
        $this->assertSame(24, DB::table('payment_plan_options')->where('is_active', false)->count());
        $this->assertSame(0, DB::table('payment_plan_options')->where('is_active', true)->count());

        $gradeOne = FeeSchedule::query()
            ->whereHas('gradeLevel', fn ($query) => $query->where('code', 'G1'))
            ->with('items.feeType')
            ->sole();
        $coreTotal = $gradeOne->items
            ->reject(fn ($item) => in_array($item->feeType->code, ['BKS', 'PEU'], true))
            ->sum(fn ($item) => (float) $item->amount);

        $this->assertSame('19903.25', number_format($coreTotal, 2, '.', ''));
    }

    public function test_it_is_idempotent_and_never_resets_existing_passwords(): void
    {
        $this->runSeeder();
        $originalHash = User::query()->where('email', 'jenny@gmail.com')->value('password');

        $this->runSeeder('A-Different-Temporary-Password-456');

        $this->assertSame(50, StudentProfile::query()->where('student_number', 'like', 'SIM-2026-%')->count());
        $this->assertSame(48, GuardianProfile::query()->where('email', 'like', 'family%@example.com')->count());
        $this->assertSame(6, DB::table('sections')->count());
        $this->assertSame(6, DB::table('fee_schedules')->count());
        $this->assertSame($originalHash, User::query()->where('email', 'jenny@gmail.com')->value('password'));
    }

    public function test_seeded_text_contains_no_disallowed_environment_label(): void
    {
        $this->runSeeder();

        foreach ([
            ['subjects', 'description'],
            ['fee_schedules', 'name'],
            ['fee_schedule_items', 'description'],
            ['enrollments', 'decision_reason'],
        ] as [$table, $column]) {
            $this->assertSame(0, DB::table($table)->where($column, 'like', '%Development test%')->count());
        }
    }

    private function runSeeder(string $password = self::TEMPORARY_PASSWORD): void
    {
        app(HostingerSampleDataSeeder::class)->run($password);
    }
}
