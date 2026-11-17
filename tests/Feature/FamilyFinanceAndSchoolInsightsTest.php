<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Billing\Calculations\BalanceCalculator;
use App\Modules\Billing\Models\AssessmentItem;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentPlan;
use App\Modules\Billing\Models\PaymentScheduleItem;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Reporting\Analytics\SchoolInsightsQuery;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/** D-064 family financial summary and D-065 school insights (pre-final panel). */
class FamilyFinanceAndSchoolInsightsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private GradeLevel $grade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-15 10:00', 'Asia/Manila'));
        $this->seed(RoleSeeder::class);
        $this->admin = $this->user(['admin']);
        $this->grade = GradeLevel::query()->create(['code' => 'FIN-G'.Str::random(4), 'name' => 'Grade 3', 'sequence' => random_int(100, 60000), 'is_active' => true]);
    }

    public function test_a_parent_sees_only_their_linked_learners_breakdown_schedule_and_payments(): void
    {
        $year = $this->year('2026-2027', '2026-06-01');
        $parent = $this->user(['guardian']);
        $guardian = GuardianProfile::query()->create(['user_id' => $parent->id, 'first_name' => 'Lorna', 'last_name' => 'Reyes']);
        $mine = $this->assessed($year, 'Mika', 'Reyes', '20000.00');
        $this->link($guardian, $mine);
        $other = $this->assessed($year, 'Stranger', 'Child', '15000.00');
        $this->plan($mine, [['2026-06-05', '10000.00'], ['2026-08-05', '5000.00'], ['2026-10-05', '5000.00']]);
        $this->pay($mine, 'REC-MINE-1', '12000.00', '2026-06-04');

        $response = $this->actingAs($parent)->get(route('billing.family.index'));
        $response->assertOk()
            ->assertSee('Mika Reyes')
            ->assertDontSee('Stranger')
            ->assertSee('PHP 20,000.00')->assertSee('PHP 12,000.00')->assertSee('PHP 8,000.00')
            ->assertSee('Tuition Fee')
            ->assertSee('REC-MINE-1')->assertDontSee('Fictional Payer')
            // 12,000 covers installment 1 (10,000) and 2,000 of installment 2, which is past due.
            ->assertSeeInOrder(['Installment 1', 'Paid', 'Installment 2', 'Overdue', '3,000.00 left', 'Installment 3', 'Upcoming'])
            ->assertSee('is past its due date');
        $this->assertDatabaseHas('audit_logs', ['action' => 'billing.financial_summary.viewed', 'actor_user_id' => $parent->id, 'subject_id' => $mine->id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'billing.financial_summary.viewed', 'subject_id' => $other->id]);

        // Staff financial pages stay staff-only (D-061).
        $this->actingAs($parent)->get(route('billing.portal.index'))->assertForbidden();
        $this->actingAs($parent)->get(route('billing.portal.show', $mine))->assertForbidden();
    }

    public function test_links_without_the_financial_view_unverified_or_ended_show_nothing(): void
    {
        $year = $this->year('2026-2027', '2026-06-01');
        $parent = $this->user(['guardian']);
        $guardian = GuardianProfile::query()->create(['user_id' => $parent->id, 'first_name' => 'No', 'last_name' => 'View']);
        $this->link($guardian, $this->assessed($year, 'Hidden', 'One', '1000.00'), ['may_view_financials' => false]);
        $this->link($guardian, $this->assessed($year, 'Hidden', 'Two', '1000.00'), ['verified_at' => null]);
        $this->link($guardian, $this->assessed($year, 'Hidden', 'Three', '1000.00'), ['ended_at' => now()]);

        $this->actingAs($parent)->get(route('billing.family.index'))->assertOk()
            ->assertDontSee('Hidden')->assertSee('No financial record yet');
    }

    public function test_a_student_sees_their_own_summary_and_staff_and_guests_cannot_open_the_family_page(): void
    {
        $year = $this->year('2026-2027', '2026-06-01');
        $learner = $this->user(['student']);
        $assessment = $this->assessed($year, 'Own', 'Learner', '5000.00');
        $assessment->enrollment->student->update(['user_id' => $learner->id]);
        $this->assessed($year, 'Someone', 'Else', '7000.00');

        $this->actingAs($learner)->get(route('billing.family.index'))->assertOk()
            ->assertSee('Own Learner')->assertDontSee('Someone')->assertSee('PHP 5,000.00')
            ->assertSee('No payment plan has been set yet');

        auth()->logout();
        $this->get(route('billing.family.index'))->assertRedirect(route('identity.login'));
        foreach (['accounting', 'teacher', 'registrar'] as $role) {
            $this->actingAs($this->user([$role]))->get(route('billing.family.index'))->assertForbidden();
        }
    }

    public function test_school_insights_compare_enrollment_and_collections_with_last_year(): void
    {
        $lastYear = $this->year('2025-2026', '2025-06-01');
        $thisYear = $this->year('2026-2027', '2026-06-01');
        $old = $this->assessed($lastYear, 'Old', 'One', '10000.00');
        $this->pay($old, 'REC-OLD-1', '4000.00', '2025-06-10');
        $first = $this->assessed($thisYear, 'New', 'One', '10000.00');
        $second = $this->assessed($thisYear, 'New', 'Two', '10000.00');
        $this->pay($first, 'REC-NEW-1', '10000.00', '2026-06-10');
        $this->pay($second, 'REC-NEW-2', '2000.00', '2026-09-03');

        $data = app(SchoolInsightsQuery::class)->for($thisYear);
        $this->assertSame(2, $data['enrolled']);
        $this->assertSame(1, $data['enrolled_last']);
        $this->assertEqualsWithDelta(20000, $data['finance']['assessed'], 0.001);
        $this->assertEqualsWithDelta(12000, $data['finance']['collected'], 0.001);
        $this->assertEqualsWithDelta(8000, $data['finance']['uncollected'], 0.001);
        $this->assertSame(60.0, $data['finance']['rate']);
        $this->assertEqualsWithDelta(4000, $data['finance_last']['collected'], 0.001);
        // June this year lines up with June last year.
        $this->assertEqualsWithDelta(10000, $data['monthly']['2026-06'], 0.001);
        $this->assertEqualsWithDelta(4000, $data['monthly_last']['2026-06'], 0.001);
        $this->assertEqualsWithDelta(2000, $data['this_month'], 0.001);

        // The aggregate matches the per-assessment BalanceCalculator.
        $calculator = app(BalanceCalculator::class);
        $due = collect([$first, $second])->sum(fn ($assessment) => (float) $calculator->calculate($assessment->fresh())['due']);
        $this->assertEqualsWithDelta($due, $data['finance']['uncollected'], 0.001);

        $this->actingAs($this->admin)->get(route('reporting.analytics', ['year' => $thisYear->public_id]))->assertOk()
            ->assertSee('School insights')->assertSee('up 100% from 1')->assertSee('PHP 12,000.00 collected of PHP 20,000.00 assessed (60%)')
            ->assertSee('up 200% from PHP 4,000.00')->assertSee('Collections by month')->assertDontSee('REC-NEW-1');
        $this->actingAs($this->user(['accounting']))->get(route('reporting.analytics.print', ['year' => $thisYear->public_id]))->assertOk()
            ->assertSee('School insights — School Year 2026-2027')->assertSee('Prepared by');
        $this->assertDatabaseHas('audit_logs', ['action' => 'reporting.analytics.viewed']);
    }

    public function test_school_insights_are_for_admin_and_accounting_only(): void
    {
        $this->year('2026-2027', '2026-06-01');
        $this->get(route('reporting.analytics'))->assertRedirect(route('identity.login'));
        foreach (['registrar', 'teacher', 'guardian', 'student'] as $role) {
            $this->actingAs($this->user([$role]))->get(route('reporting.analytics'))->assertForbidden();
        }
        $this->actingAs($this->admin)->get(route('reporting.analytics', ['year' => 'not-a-year']))->assertRedirect()->assertSessionHasErrors('year');
    }

    private function year(string $code, string $starts): AcademicYear
    {
        $start = Carbon::parse($starts);

        return AcademicYear::query()->create(['code' => $code.'-'.Str::random(3), 'name' => 'School Year '.$code, 'starts_on' => $start, 'ends_on' => $start->copy()->addMonths(10), 'enrollment_opens_on' => $start->copy()->subMonth(), 'status' => 'open']);
    }

    private function assessed(AcademicYear $year, string $first, string $last, string $net): FeeAssessment
    {
        $student = StudentProfile::query()->create(['student_number' => 'JCA-26-'.random_int(100, 999).Str::random(2), 'first_name' => $first, 'last_name' => $last, 'sex' => 'female']);
        $enrollment = Enrollment::query()->create(['application_number' => 'APP-'.Str::random(10), 'student_id' => $student->id, 'academic_year_id' => $year->id, 'grade_level_id' => $this->grade->id, 'student_category' => 'regular', 'status' => 'enrolled', 'active_enrollment_key' => $student->id.':'.$year->id, 'submitted_at' => now(), 'approved_at' => now(), 'enrolled_at' => now()]);
        $schedule = FeeSchedule::query()->firstOrCreate(['academic_year_id' => $year->id, 'grade_level_id' => $this->grade->id, 'version' => 1], ['name' => 'Schedule', 'status' => 'published', 'published_by' => $this->admin->id, 'published_at' => now()]);
        $assessment = FeeAssessment::query()->create(['assessment_reference' => 'ASM-'.Str::random(8), 'enrollment_id' => $enrollment->id, 'fee_schedule_id' => $schedule->id, 'revision_number' => 1, 'status' => 'draft', 'gross_total' => $net, 'discount_total' => '0.00', 'net_total' => $net, 'created_by' => $this->admin->id]);
        // Items can only be added while the assessment is a draft; then it is finalized (and immutable).
        AssessmentItem::query()->create(['fee_assessment_id' => $assessment->id, 'fee_code' => 'TUI', 'fee_name' => 'Tuition Fee', 'category' => 'tuition', 'quantity' => 1, 'unit_amount' => $net, 'gross_amount' => $net, 'discount_amount' => '0.00', 'net_amount' => $net]);
        $assessment->update(['status' => 'finalized', 'finalized_by' => $this->admin->id, 'finalized_at' => now(), 'current_finalized_key' => (string) $enrollment->id]);

        return $assessment->fresh();
    }

    /** @param list<array{0: string, 1: string}> $installments */
    private function plan(FeeAssessment $assessment, array $installments): void
    {
        $plan = PaymentPlan::query()->create(['fee_assessment_id' => $assessment->id, 'plan_type' => 'quarterly', 'status' => 'active', 'selected_by' => $this->admin->id, 'selected_at' => now()]);
        foreach ($installments as $index => [$due, $amount]) {
            PaymentScheduleItem::query()->create(['payment_plan_id' => $plan->id, 'installment_number' => $index + 1, 'label' => 'Installment '.($index + 1), 'due_on' => $due, 'amount' => $amount]);
        }
    }

    private function pay(FeeAssessment $assessment, string $receipt, string $amount, string $on): void
    {
        Payment::query()->create(['payment_reference' => 'PAY-'.$receipt, 'receipt_reference' => $receipt, 'fee_assessment_id' => $assessment->id, 'received_on' => $on, 'amount' => $amount, 'method' => 'cash', 'payer_name' => 'Fictional Payer', 'status' => 'posted', 'created_by' => $this->admin->id]);
    }

    /** @param array<string, mixed> $overrides */
    private function link(GuardianProfile $guardian, FeeAssessment $assessment, array $overrides = []): void
    {
        GuardianStudent::query()->create([...[
            'guardian_id' => $guardian->id, 'student_id' => $assessment->enrollment->student_id, 'relationship_type' => 'mother',
            'may_manage_enrollment' => true, 'may_view_financials' => true, 'verified_at' => now(), 'effective_from' => today(),
        ], ...$overrides]);
    }

    /** @param list<string> $roles */
    private function user(array $roles): User
    {
        $user = User::query()->create(['name' => 'Fictional '.Str::random(6), 'email' => Str::lower(Str::random(12)).'@example.test', 'password' => Hash::make('Password12345'), 'status' => AccountStatus::Active, 'password_changed_at' => now()]);

        return app(AssignRolesAction::class)->execute($user, $roles, null);
    }
}
