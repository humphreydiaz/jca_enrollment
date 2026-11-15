<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Billing\Actions\CreateAssessmentAction;
use App\Modules\Billing\Actions\FinalizeAssessmentAction;
use App\Modules\Billing\Actions\SelectPaymentPlanAction;
use App\Modules\Billing\Models\ExamPeriod;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Billing\Models\FeeScheduleItem;
use App\Modules\Billing\Models\FeeType;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use Database\Seeders\Billing\SchoolCalendarExamPeriodsSeeder;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    public function test_a_new_draft_can_start_from_another_years_published_fees_and_be_adjusted(): void
    {
        $accounting = $this->staff('accounting');
        $lastYear = $this->year();
        $nextYear = $this->year();
        $grade7 = $this->grade('Grade 7');
        $grade8 = $this->grade('Grade 8');
        $source = $this->schedule($lastYear, $grade7, 'draft');
        $tuition = $this->item($source, '18500.00');
        $books = $this->item($source, '3423.00');
        $retired = $this->item($source, '150.00');
        $retired->feeType->update(['is_active' => false]);
        $source->update(['status' => 'published', 'published_at' => now()]);

        $this->actingAs($accounting)->get(route('billing.configuration.index'))->assertOk()
            ->assertSee('Start from')->assertSee('Use as template')->assertSee('value="'.$source->public_id.'"', false);

        $this->actingAs($accounting)->post(route('billing.configuration.schedules'), [
            'academic_year_id' => $nextYear->id, 'grade_level_id' => $grade8->id, 'name' => 'Grade 8 SY 2027-2028', 'copy_from' => $source->public_id,
        ])->assertRedirect()->assertSessionHas('status', fn (string $message) => str_contains($message, 'with 2 fees copied from Fee schedule (version 1)') && str_contains($message, '1 inactive fee left out'));

        $copy = FeeSchedule::query()->where('name', 'Grade 8 SY 2027-2028')->sole();
        $this->assertSame('draft', $copy->status);
        $this->assertSame([$nextYear->id, $grade8->id, 1], [$copy->academic_year_id, $copy->grade_level_id, $copy->version]);
        $this->assertEqualsCanonicalizing([$tuition->fee_type_id, $books->fee_type_id], $copy->items()->pluck('fee_type_id')->all());
        $this->assertSame('18500.00', (string) $copy->items()->where('fee_type_id', $tuition->fee_type_id)->value('amount'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'billing.fee_schedule.created', 'subject_id' => $copy->id]);

        // The copy is an ordinary draft: amounts can change and fees can be removed, while the source stays intact.
        $copiedTuition = $copy->items()->where('fee_type_id', $tuition->fee_type_id)->sole();
        $this->actingAs($accounting)->put(route('billing.configuration.schedule-items.update', [$copy, $copiedTuition]), [
            'description' => 'Tuition', 'amount' => '19500.00', 'price_status' => 'final', 'is_required' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($accounting)->delete(route('billing.configuration.schedule-items.destroy', [$copy, $copy->items()->where('fee_type_id', $books->fee_type_id)->sole()]))->assertRedirect();
        $this->assertSame(['19500.00'], $copy->items()->pluck('amount')->map(fn ($amount) => (string) $amount)->all());
        $this->assertSame(3, $source->items()->count());
        $this->assertSame('18500.00', (string) $tuition->fresh()->amount);

        // A blank start still works, and an unknown source is rejected.
        $this->actingAs($accounting)->post(route('billing.configuration.schedules'), ['academic_year_id' => $nextYear->id, 'grade_level_id' => $grade7->id, 'name' => 'Blank', 'copy_from' => ''])->assertRedirect();
        $this->assertSame(0, FeeSchedule::query()->where('name', 'Blank')->sole()->items()->count());
        $this->actingAs($accounting)->post(route('billing.configuration.schedules'), ['academic_year_id' => $nextYear->id, 'name' => 'Bad', 'copy_from' => '01J0000000000000000000BAD0'])->assertSessionHasErrors('copy_from');
        $this->actingAs($this->staff('registrar'))->post(route('billing.configuration.schedules'), ['academic_year_id' => $nextYear->id, 'name' => 'Nope', 'copy_from' => $source->public_id])->assertForbidden();
    }

    public function test_school_calendar_exam_periods_enable_the_quarterly_plan(): void
    {
        $accounting = $this->staff('accounting');
        [$enrollment, $schedule] = $this->assessableFixture();
        $enrollment->academicYear->update(['code' => '2026-2027', 'starts_on' => '2026-06-08', 'ends_on' => '2027-04-08']);
        $previousYear = AcademicYear::query()->create(['code' => '2025-2026', 'name' => 'School Year 2025-2026', 'starts_on' => '2025-06-09', 'ends_on' => '2026-04-08', 'status' => 'closed']);
        $assessment = app(CreateAssessmentAction::class)->execute($enrollment, $schedule, [], $accounting);
        app(FinalizeAssessmentAction::class)->execute($assessment, $accounting);

        $this->expectValidationMessage(fn () => app(SelectPaymentPlanAction::class)->execute($assessment->fresh(), 'quarterly', $accounting), 'require configured examination periods');

        $this->seed(SchoolCalendarExamPeriodsSeeder::class);
        $this->seed(SchoolCalendarExamPeriodsSeeder::class);

        // SY 2026-2027: exactly the JCSGO School Calendar 2026-2027 quarterly examinations.
        $current = ExamPeriod::query()->where('academic_year_id', $enrollment->academic_year_id)->orderBy('starts_on')->get();
        $this->assertSame(['Q1', 'Q2', 'Q3', 'Q4'], $current->pluck('code')->all(), 'Four periods, and a second run adds nothing.');
        $this->assertSame(
            [['2026-08-19', '2026-08-20'], ['2026-10-19', '2026-10-20'], ['2027-01-21', '2027-01-22'], ['2027-03-15', '2027-03-16']],
            $current->map(fn (ExamPeriod $period) => [$period->starts_on->toDateString(), $period->ends_on->toDateString()])->all(),
        );
        $this->assertSame('2026-08-09', $current->first()->eligibility_cutoff_on->toDateString());
        $this->assertSame('2026-07-20', $current->first()->issue_opens_on->toDateString());
        $this->assertSame('Fourth Quarterly Examination', $current->last()->name);

        // SY 2025-2026 follows the same pattern, and every period falls on school days.
        $previous = ExamPeriod::query()->where('academic_year_id', $previousYear->id)->orderBy('starts_on')->get();
        $this->assertSame(['2025-08-19', '2025-10-20', '2026-01-22', '2026-03-16'], $previous->map(fn (ExamPeriod $period) => $period->starts_on->toDateString())->all());
        $this->assertTrue($previous->every(fn (ExamPeriod $period) => $period->status === 'closed' && $period->starts_on->isWeekday() && $period->ends_on->isWeekday()));

        $plan = app(SelectPaymentPlanAction::class)->execute($assessment->fresh(), 'quarterly', $accounting);
        $this->assertSame('quarterly', $plan->plan_type);
        $this->assertNotEmpty($plan->scheduleItems);
    }

    public function test_examination_periods_are_grouped_by_school_year_with_plan_readiness(): void
    {
        $accounting = $this->staff('accounting');
        $current = AcademicYear::query()->create(['code' => '2026-2027', 'name' => 'School Year 2026-2027', 'starts_on' => today()->subMonths(4), 'ends_on' => today()->addMonths(6), 'status' => 'open']);
        $past = AcademicYear::query()->create(['code' => '2024-2025', 'name' => 'School Year 2024-2025', 'starts_on' => today()->subMonths(16), 'ends_on' => today()->subMonths(6), 'status' => 'closed']);
        $this->seed(SchoolCalendarExamPeriodsSeeder::class);
        ExamPeriod::query()->create(['academic_year_id' => $past->id, 'code' => 'OLD1', 'name' => 'Only Old Exam', 'starts_on' => today()->subMonths(10), 'ends_on' => today()->subMonths(10), 'eligibility_cutoff_on' => today()->subMonths(10)->subDays(10), 'status' => 'closed']);

        $response = $this->actingAs($accounting)->get(route('billing.configuration.index'))->assertOk();
        $response->assertSee('id="exam-tab-'.$current->id.'"', false)->assertSee('id="exam-tab-'.$past->id.'"', false)
            ->assertSee('Current')->assertSee('Only Old Exam')
            ->assertSee('Quarterly needs 3 more · Semiannual needs 2 more')
            ->assertSee('Add an examination period');
        // The current year opens first; the past year's panel starts hidden.
        $this->assertMatchesRegularExpression('/id="exam-panel-'.$past->id.'"[^>]*x-cloak/', $response->getContent());
        $this->assertDoesNotMatchRegularExpression('/id="exam-panel-'.$current->id.'"[^>]*x-cloak/', $response->getContent());
    }

    private function expectValidationMessage(callable $callback, string $fragment): void
    {
        try {
            $callback();
            $this->fail('Expected a validation error.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($fragment, collect($exception->errors())->flatten()->implode(' '));
        }
    }

    public function test_only_admin_and_accounting_may_manage_fee_schedule_versions(): void
    {
        $schedule = $this->schedule($this->year(), $this->grade('Grade 3'), 'draft');

        foreach (['registrar', 'teacher'] as $role) {
            $actor = $this->staff($role);

            $this->actingAs($actor)->put(route('billing.configuration.schedules.update', $schedule), [
                'academic_year_id' => $schedule->academic_year_id,
                'name' => 'Attempted rename',
            ])->assertForbidden();

            $this->actingAs($actor)->delete(route('billing.configuration.schedules.destroy', $schedule))->assertForbidden();
            $this->actingAs($actor)->post(route('billing.configuration.schedules.archive', $schedule))->assertForbidden();
        }

        $this->assertDatabaseHas('fee_schedules', ['id' => $schedule->id, 'name' => 'Fee schedule']);

        // Accounting maintains financial configuration alongside the Admin (D-046).
        $accounting = $this->staff('accounting');
        $this->actingAs($accounting)->get(route('billing.configuration.index'))->assertOk()->assertSee('Finance workspace');
        $this->actingAs($accounting)->put(route('billing.configuration.schedules.update', $schedule), [
            'academic_year_id' => $schedule->academic_year_id,
            'name' => 'Accounting rename',
        ])->assertRedirect();
        $this->assertDatabaseHas('fee_schedules', ['id' => $schedule->id, 'name' => 'Accounting rename']);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $accounting->id]);
        $this->actingAs($accounting)->get(route('identity.dashboard'))->assertSee(route('billing.configuration.index'), false);
        $this->actingAs($this->staff('registrar'))->get(route('identity.dashboard'))->assertDontSee(route('billing.configuration.index'), false);
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
