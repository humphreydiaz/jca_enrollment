<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Billing\Models\FeeScheduleItem;
use App\Modules\Billing\Models\FeeType;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use Database\Seeders\Billing\CurrentSchoolYearJuniorHighBillingTestSeeder;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JuniorHighBillingConfigurationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_populates_only_configured_junior_high_grades_and_is_idempotent(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::query()->create([
            'name' => 'Development Billing Admin',
            'email' => 'development.billing.admin@example.test',
            'password' => Hash::make('Password12345'),
            'status' => AccountStatus::Active,
            'password_changed_at' => now(),
        ]);
        app(AssignRolesAction::class)->execute($admin, ['admin'], null);
        AcademicYear::query()->create([
            'code' => '2026-2027',
            'name' => 'School Year 2026-2027',
            'starts_on' => '2026-06-08',
            'ends_on' => '2027-04-08',
            'status' => 'open',
        ]);

        foreach ([
            ['G2', 'Grade 2', 2, 'Elementary'],
            ['G7', 'Grade 7', 7, 'Junior High School'],
            ['G8', 'Grade 8', 8, 'Junior High School'],
            ['G9', 'Grade 9', 9, 'Junior High School'],
            ['G11', 'Grade 11', 11, 'Senior High School'],
        ] as [$code, $name, $sequence, $stage]) {
            GradeLevel::query()->create([
                'code' => $code,
                'name' => $name,
                'sequence' => $sequence,
                'school_stage' => $stage,
                'is_active' => true,
            ]);
        }

        $this->seed(CurrentSchoolYearJuniorHighBillingTestSeeder::class);
        $this->seed(CurrentSchoolYearJuniorHighBillingTestSeeder::class);

        $this->assertDatabaseCount('fee_types', 7);
        $this->assertDatabaseCount('fee_schedules', 3);
        $this->assertDatabaseCount('fee_schedule_items', 18);
        $this->assertDatabaseCount('payment_plan_options', 12);
        $this->assertDatabaseCount('exam_periods', 4);
        $this->assertDatabaseCount('school_non_business_days', 2);
        $this->assertDatabaseCount('discount_campaigns', 1);
        $this->assertDatabaseCount('financial_print_settings', 4);

        $tuition = FeeScheduleItem::query()
            ->where('fee_type_id', FeeType::query()->where('code', 'TUI')->value('id'))
            ->firstOrFail();
        $this->assertSame('27000.00', $tuition->amount);
        $this->assertTrue($tuition->is_required);
        $this->assertTrue($tuition->is_default_selected);

        $this->assertDatabaseMissing('fee_schedules', [
            'grade_level_id' => GradeLevel::query()->where('code', 'G2')->value('id'),
        ]);
        $this->assertDatabaseMissing('fee_schedules', [
            'grade_level_id' => GradeLevel::query()->where('code', 'G11')->value('id'),
        ]);
        $this->assertDatabaseHas('discount_campaigns', [
            'code' => 'DEV-JHS-FIRST-100',
            'is_active' => false,
            'is_development_fixture' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'billing.development_jhs_configuration.seeded',
        ]);
    }
}
