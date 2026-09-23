<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Billing\Actions\CreateAssessmentAction;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Billing\Models\FeeScheduleItem;
use App\Modules\Billing\Models\FeeType;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class FeeScheduleAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private static int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        self::$sequence = 0;
    }

    public function test_admin_can_correct_the_grade_level_of_a_draft_without_publishing_it(): void
    {
        $admin = $this->admin();
        $year = $this->year();
        $wrongGrade = $this->grade('Grade 3');
        $rightGrade = $this->grade('Grade 4');
        $schedule = $this->schedule($year, $wrongGrade, 'draft');

        $this->actingAs($admin)
            ->put(route('billing.configuration.schedules.update', $schedule), [
                'academic_year_id' => $year->id,
                'grade_level_id' => $rightGrade->id,
                'name' => 'Grade 4 ELEM SY 2026-2027',
            ])
            ->assertRedirect();

        $fresh = $schedule->fresh();
        $this->assertSame($rightGrade->id, $fresh->grade_level_id);
        $this->assertSame('Grade 4 ELEM SY 2026-2027', $fresh->name);
        $this->assertSame('draft', $fresh->status);
    }

    public function test_a_published_version_cannot_be_edited(): void
    {
        $admin = $this->admin();
        $year = $this->year();
        $grade = $this->grade('Grade 3');
        $schedule = $this->schedule($year, $grade, 'published');

        $this->actingAs($admin)
            ->put(route('billing.configuration.schedules.update', $schedule), [
                'academic_year_id' => $year->id,
                'grade_level_id' => $grade->id,
                'name' => 'Renamed after publishing',
            ])
            ->assertSessionHasErrors('schedule');

        $this->assertSame('Fee schedule', $schedule->fresh()->name);
    }

    public function test_admin_can_delete_a_draft_together_with_its_items(): void
    {
        $admin = $this->admin();
        $schedule = $this->schedule($this->year(), $this->grade('Grade 3'), 'draft');
        $item = $this->item($schedule);

        $this->actingAs($admin)
            ->delete(route('billing.configuration.schedules.destroy', $schedule))
            ->assertRedirect();

        $this->assertDatabaseMissing('fee_schedules', ['id' => $schedule->id]);
        $this->assertDatabaseMissing('fee_schedule_items', ['id' => $item->id]);
    }

    public function test_a_published_version_with_no_assessment_can_be_deleted(): void
    {
        $admin = $this->admin();
        $schedule = $this->schedule($this->year(), $this->grade('Grade 3'), 'published');
        $item = $this->item($schedule);

        $this->actingAs($admin)
            ->delete(route('billing.configuration.schedules.destroy', $schedule))
            ->assertRedirect();

        $this->assertDatabaseMissing('fee_schedules', ['id' => $schedule->id]);
        $this->assertDatabaseMissing('fee_schedule_items', ['id' => $item->id]);
    }

    public function test_a_version_already_used_by_an_assessment_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $accounting = $this->staff('accounting');
        [$enrollment, $schedule] = $this->assessableFixture();

        app(CreateAssessmentAction::class)->execute($enrollment, $schedule, [], $accounting);

        $this->actingAs($admin)
            ->delete(route('billing.configuration.schedules.destroy', $schedule))
            ->assertSessionHasErrors('schedule');

        $this->assertDatabaseHas('fee_schedules', ['id' => $schedule->id]);
    }

    public function test_archiving_withdraws_a_version_from_new_assessments_and_restoring_returns_it(): void
    {
        $admin = $this->admin();
        $schedule = $this->schedule($this->year(), $this->grade('Grade 3'), 'published');
        $this->item($schedule);

        $this->actingAs($admin)
            ->post(route('billing.configuration.schedules.archive', $schedule))
            ->assertRedirect();
        $this->assertSame('archived', $schedule->fresh()->status);

        // Every assessment path offers published schedules only.
        $this->assertFalse(
            FeeSchedule::query()->where('status', 'published')->whereKey($schedule->id)->exists(),
        );

        $this->actingAs($admin)
            ->post(route('billing.configuration.schedules.restore', $schedule))
            ->assertRedirect();
        $this->assertSame('published', $schedule->fresh()->status);
    }

    public function test_an_archived_version_still_used_by_an_assessment_keeps_its_history(): void
    {
        $admin = $this->admin();
        $accounting = $this->staff('accounting');
        [$enrollment, $schedule] = $this->assessableFixture();
        $assessment = app(CreateAssessmentAction::class)->execute($enrollment, $schedule, [], $accounting);

        $this->actingAs($admin)->post(route('billing.configuration.schedules.archive', $schedule))->assertRedirect();

        $this->assertDatabaseHas('fee_assessments', ['id' => $assessment->id, 'fee_schedule_id' => $schedule->id]);
        $this->assertSame('archived', $schedule->fresh()->status);
    }

    public function test_version_numbers_are_assigned_per_year_and_grade(): void
    {
        $admin = $this->admin();
        $year = $this->year();
        $grade3 = $this->grade('Grade 3');
        $grade4 = $this->grade('Grade 4');

        $create = fn (array $payload) => $this->actingAs($admin)
            ->post(route('billing.configuration.schedules'), $payload)
            ->assertRedirect();

        $create(['academic_year_id' => $year->id, 'grade_level_id' => $grade3->id, 'name' => 'G3 first']);
        $create(['academic_year_id' => $year->id, 'grade_level_id' => $grade3->id, 'name' => 'G3 second']);
        $create(['academic_year_id' => $year->id, 'grade_level_id' => $grade4->id, 'name' => 'G4 first']);
        $create(['academic_year_id' => $year->id, 'name' => 'All grades first']);

        $this->assertSame(1, FeeSchedule::query()->where('name', 'G3 first')->value('version'));
        $this->assertSame(2, FeeSchedule::query()->where('name', 'G3 second')->value('version'));
        // A different grade starts its own series.
        $this->assertSame(1, FeeSchedule::query()->where('name', 'G4 first')->value('version'));
        // So does an all-grades schedule.
        $this->assertSame(1, FeeSchedule::query()->where('name', 'All grades first')->value('version'));
    }

    public function test_moving_a_draft_to_another_grade_renumbers_it_into_that_series(): void
    {
        $admin = $this->admin();
        $year = $this->year();
        $grade3 = $this->grade('Grade 3');
        $grade4 = $this->grade('Grade 4');

        $existing = $this->schedule($year, $grade4, 'published');
        $existing->forceFill(['version' => 1])->save();

        $draft = $this->schedule($year, $grade3, 'draft');
        $draft->forceFill(['version' => 1])->save();

        $this->actingAs($admin)
            ->put(route('billing.configuration.schedules.update', $draft), [
                'academic_year_id' => $year->id,
                'grade_level_id' => $grade4->id,
                'name' => 'Moved to Grade 4',
            ])
            ->assertRedirect();

        $this->assertSame(2, $draft->fresh()->version);
    }

    public function test_the_configuration_page_shows_the_year_grade_and_usage_of_each_version(): void
    {
        $admin = $this->admin();
        $accounting = $this->staff('accounting');
        [$enrollment, $schedule] = $this->assessableFixture();
        app(CreateAssessmentAction::class)->execute($enrollment, $schedule, [], $accounting);

        $this->actingAs($admin)
            ->get(route('billing.configuration.index'))
            ->assertOk()
            ->assertSee($schedule->gradeLevel->name)
            ->assertSee($schedule->academicYear->name)
            ->assertSee('In use by 1 student assessment')
            ->assertSee('Delete unavailable');
    }

    public function test_each_version_shows_a_total_row_for_its_fee_items(): void
    {
        $admin = $this->admin();
        $schedule = $this->schedule($this->year(), $this->grade('Grade 3'), 'published');
        $this->item($schedule);          // 3,423.00
        $this->item($schedule, '1577.00');

        // A draft carries an extra action column, so its total row spans differently.
        $draft = $this->schedule($this->year(), $this->grade('Grade 4'), 'draft');
        $this->item($draft, '2500.50');

        $this->actingAs($admin)
            ->get(route('billing.configuration.index'))
            ->assertOk()
            ->assertSee('Total')
            ->assertSee('PHP 5,000.00')
            ->assertSee('PHP 2,500.50');
    }

    public function test_only_an_admin_may_manage_fee_schedule_versions(): void
    {
        $schedule = $this->schedule($this->year(), $this->grade('Grade 3'), 'draft');

        foreach (['accounting', 'registrar', 'teacher'] as $role) {
            $actor = $this->staff($role);

            $this->actingAs($actor)->put(route('billing.configuration.schedules.update', $schedule), [
                'academic_year_id' => $schedule->academic_year_id,
                'name' => 'Attempted rename',
            ])->assertForbidden();

            $this->actingAs($actor)->delete(route('billing.configuration.schedules.destroy', $schedule))->assertForbidden();
            $this->actingAs($actor)->post(route('billing.configuration.schedules.archive', $schedule))->assertForbidden();
        }

        $this->assertDatabaseHas('fee_schedules', ['id' => $schedule->id, 'name' => 'Fee schedule']);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function year(): AcademicYear
    {
        $suffix = Str::upper(Str::random(6));

        return AcademicYear::query()->create([
            'code' => 'FS-'.$suffix,
            'name' => 'School Year 2026-2027',
            'starts_on' => '2026-06-01',
            'ends_on' => '2027-03-31',
            'status' => 'open',
        ]);
    }

    private function grade(string $name): GradeLevel
    {
        $sequence = ++self::$sequence;

        return GradeLevel::query()->create([
            'code' => 'FS-G'.$sequence.'-'.Str::random(4),
            'name' => $name,
            'sequence' => $sequence,
            'school_stage' => 'Elementary',
            'is_active' => true,
        ]);
    }

    private function schedule(AcademicYear $year, ?GradeLevel $grade, string $status): FeeSchedule
    {
        return FeeSchedule::query()->create([
            'academic_year_id' => $year->id,
            'grade_level_id' => $grade?->id,
            'name' => 'Fee schedule',
            'version' => 1,
            'status' => $status,
        ]);
    }

    private function item(FeeSchedule $schedule, string $amount = '3423.00'): FeeScheduleItem
    {
        $type = FeeType::query()->create([
            'code' => 'FS-'.Str::upper(Str::random(5)),
            'name' => 'Books',
            'category' => 'School Fee',
            'is_mandatory' => true,
        ]);

        return FeeScheduleItem::query()->create([
            'fee_schedule_id' => $schedule->id,
            'fee_type_id' => $type->id,
            'description' => 'Books',
            'amount' => $amount,
            'is_required' => true,
        ]);
    }

    /** @return array{0: Enrollment, 1: FeeSchedule} */
    private function assessableFixture(): array
    {
        $year = $this->year();
        $grade = $this->grade('Grade 3');
        $student = StudentProfile::query()->create(['first_name' => 'Fictional', 'last_name' => 'Student']);
        $guardian = GuardianProfile::query()->create(['first_name' => 'Fictional', 'last_name' => 'Guardian']);
        $enrollment = Enrollment::query()->create([
            'application_number' => 'FS-'.Str::upper(Str::random(8)),
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'grade_level_id' => $grade->id,
            'applicant_guardian_id' => $guardian->id,
            'student_category' => 'regular',
            'status' => 'approved',
            'approved_at' => now(),
            'active_enrollment_key' => $student->id.':'.$year->id,
        ]);

        $schedule = $this->schedule($year, $grade, 'published');
        $this->item($schedule);

        return [$enrollment, $schedule];
    }

    private function admin(): User
    {
        return $this->staff('admin');
    }

    private function staff(string $role): User
    {
        $user = User::query()->create([
            'name' => 'Staff '.Str::random(5),
            'email' => Str::lower(Str::random(10)).'@example.test',
            'password' => Hash::make('StaffPass12345'),
            'status' => AccountStatus::Active,
            'password_changed_at' => now(),
        ]);

        return app(AssignRolesAction::class)->execute($user, [$role], null);
    }
}
