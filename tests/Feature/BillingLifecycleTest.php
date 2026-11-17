<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Billing\Actions\AllocateStudentCreditAction;
use App\Modules\Billing\Actions\ApproveAdjustmentAction;
use App\Modules\Billing\Actions\ApprovePermitOverrideAction;
use App\Modules\Billing\Actions\AwardDiscountAction;
use App\Modules\Billing\Actions\CancelDraftFinancialRecordAction;
use App\Modules\Billing\Actions\CreateAssessmentAction;
use App\Modules\Billing\Actions\CreateDraftPaymentAction;
use App\Modules\Billing\Actions\DecideDiscountCancellationAction;
use App\Modules\Billing\Actions\FinalizeAssessmentAction;
use App\Modules\Billing\Actions\IssueExamPermitAction;
use App\Modules\Billing\Actions\PostPaymentAction;
use App\Modules\Billing\Actions\RequestAdjustmentAction;
use App\Modules\Billing\Actions\RevokeExamPermitAction;
use App\Modules\Billing\Actions\RevokePermitOverrideAction;
use App\Modules\Billing\Actions\SelectPaymentPlanAction;
use App\Modules\Billing\Actions\UpdateDraftAssessmentAction;
use App\Modules\Billing\Actions\UpdateDraftPaymentAction;
use App\Modules\Billing\Calculations\Money;
use App\Modules\Billing\Models\DiscountAward;
use App\Modules\Billing\Models\DiscountCampaign;
use App\Modules\Billing\Models\ExamPeriod;
use App\Modules\Billing\Models\ExamPermitOverride;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Billing\Models\FeeScheduleItem;
use App\Modules\Billing\Models\FeeType;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentAllocation;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BillingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_draft_assessment_and_payment_are_idempotent_editable_then_immutable(): void
    {
        $accounting = $this->user(['accounting']);
        [$enrollment, $schedule, $books] = $this->fixture();
        $assessment = app(CreateAssessmentAction::class)->execute($enrollment, $schedule, [], $accounting);
        $duplicate = app(CreateAssessmentAction::class)->execute($enrollment, $schedule, [], $accounting);
        $this->assertSame($assessment->id, $duplicate->id);
        $this->assertSame(1, FeeAssessment::query()->where('enrollment_id', $enrollment->id)->where('status', 'draft')->count());
        app(UpdateDraftAssessmentAction::class)->execute($assessment, $schedule, [$books->id], 'Fictional optional books selected', $accounting);
        $this->assertSame('33000.00', (string) $assessment->fresh()->net_total);
        $this->assertDatabaseHas('assessment_items', ['fee_assessment_id' => $assessment->id, 'fee_code' => 'BKS']);
        app(FinalizeAssessmentAction::class)->execute($assessment, $accounting);
        try {
            $assessment->fresh()->update(['net_total' => '1.00']);
            $this->fail('Finalized assessment totals must be immutable.');
        } catch (\LogicException) {
            $this->assertTrue(true);
        }

        $payment = app(CreateDraftPaymentAction::class)->execute($assessment->fresh(), ['received_on' => '2026-06-01', 'amount' => '30000.00', 'method' => 'cash', 'payer_name' => 'Fictional Guardian'], $accounting);
        $this->assertSame('30000.00', Money::sum($payment->allocations->pluck('amount')));
        app(UpdateDraftPaymentAction::class)->execute($payment, ['received_on' => '2026-06-02', 'amount' => '100.00', 'method' => 'cash', 'payer_name' => 'Corrected Fictional Guardian', 'assessment_item_id' => $assessment->items()->where('fee_code', 'MSC')->value('id')], $accounting);
        $this->assertSame('100.00', (string) $payment->fresh()->amount);
        $this->assertSame(1, $payment->allocations()->count());
        app(PostPaymentAction::class)->execute($payment, $accounting);
        $cancelledPayment = app(CreateDraftPaymentAction::class)->execute($assessment->fresh(), ['received_on' => '2026-06-03', 'amount' => '50.00', 'method' => 'cash', 'payer_name' => 'Fictional Cancelled Payer'], $accounting);
        app(CancelDraftFinancialRecordAction::class)->payment($cancelledPayment, 'Fictional draft cancellation', $accounting);
        $this->assertSame('cancelled', $cancelledPayment->fresh()->status);
        $this->assertSame('Fictional draft cancellation', $cancelledPayment->fresh()->cancellation_reason);
        $otherEnrollment = $this->additionalEnrollment($enrollment, 'CANCEL', now()->addMinute());
        $cancelledAssessment = app(CreateAssessmentAction::class)->execute($otherEnrollment, $schedule, [], $accounting);
        app(CancelDraftFinancialRecordAction::class)->assessment($cancelledAssessment, 'Fictional assessment cancellation', $accounting);
        $this->assertSame('cancelled', $cancelledAssessment->fresh()->status);
        $this->expectException(\LogicException::class);
        $payment->fresh()->update(['payer_name' => 'Forbidden edit']);
    }

    public function test_accounting_sees_a_repeated_draft_and_cancels_it_from_the_billing_page(): void
    {
        $accounting = $this->user(['accounting']);
        [$enrollment, $schedule] = $this->fixture();
        $assessment = app(CreateAssessmentAction::class)->execute($enrollment, $schedule, [], $accounting);
        app(FinalizeAssessmentAction::class)->execute($assessment, $accounting);
        // Both the posted payment and its repeat fit within the remaining balance.
        $data = ['received_on' => '2026-08-10', 'amount' => '5000.00', 'method' => 'cash', 'payer_name' => 'Fictional Uncle'];
        $posted = app(PostPaymentAction::class)->execute(app(CreateDraftPaymentAction::class)->execute($assessment->fresh(), $data, $accounting), $accounting);
        $repeat = app(CreateDraftPaymentAction::class)->execute($assessment->fresh(), $data, $accounting);

        $this->actingAs($accounting)->get(route('billing.index'))->assertOk()
            ->assertSee('Possible duplicate:')->assertSee($posted->fresh()->receipt_reference)
            ->assertSee('Cancel draft payment')->assertSee(route('billing.payments.cancel', $repeat), false);

        $this->actingAs($accounting)->post(route('billing.payments.cancel', $repeat), ['reason' => 'Duplicate of '.$posted->fresh()->receipt_reference])
            ->assertSessionHas('status', 'Draft payment cancelled; history retained.');
        $this->assertSame('cancelled', $repeat->fresh()->status);
        $this->assertSame('posted', $posted->fresh()->status);
        $this->actingAs($accounting)->get(route('billing.index'))->assertOk()->assertSee('No draft payments')->assertDontSee('Possible duplicate:');
        $this->assertDatabaseHas('audit_logs', ['action' => 'billing.payment.draft_cancelled']);
    }

    public function test_cancelled_discount_slot_is_reopened_only_by_admin_and_awarded_next(): void
    {
        $accounting = $this->user(['accounting']);
        $admin = $this->user(['admin']);
        [$first] = $this->fixture();
        $second = $this->additionalEnrollment($first, 'SECOND', now()->addMinute());
        $third = $this->additionalEnrollment($first, 'THIRD', now()->addMinutes(2));
        $campaign = DiscountCampaign::query()->create(['academic_year_id' => $first->academic_year_id, 'code' => 'REOPEN', 'name' => 'Fictional first one', 'value_type' => 'fixed', 'value_amount' => '1000.00', 'slot_limit' => 1, 'starts_on' => today()->subDay(), 'ends_on' => today()->addDay(), 'is_active' => true, 'is_development_fixture' => true]);
        $firstAward = app(AwardDiscountAction::class)->execute($campaign, $first, $accounting);
        $action = app(DecideDiscountCancellationAction::class);
        $action->request($firstAward, 'Fictional cancellation request', $accounting);
        $action->decide($firstAward, true, 'Fictional Admin approval', $admin);
        $nextAward = $action->reopenSlot($firstAward->fresh(), 'Fictional reopened slot', $admin);
        $this->assertSame($second->id, $nextAward?->enrollment_id);
        $this->assertSame(2, $nextAward?->rank);
        $this->assertNull(DiscountAward::query()->where('enrollment_id', $third->id)->first());
        $this->assertDatabaseHas('discount_award_events', ['discount_award_id' => $firstAward->id, 'event_type' => 'slot_reopened']);
    }

    public function test_permit_issue_window_revocation_replacement_and_override_history(): void
    {
        $accounting = $this->user(['accounting']);
        $admin = $this->user(['admin']);
        [$enrollment, $schedule] = $this->fixture();
        $assessment = app(CreateAssessmentAction::class)->execute($enrollment, $schedule, [], $accounting);
        app(FinalizeAssessmentAction::class)->execute($assessment, $accounting);
        app(SelectPaymentPlanAction::class)->execute($assessment, 'full', $accounting);
        $payment = app(CreateDraftPaymentAction::class)->execute($assessment->fresh(), ['received_on' => today(), 'amount' => '28500.00', 'method' => 'cash', 'payer_name' => 'Fictional Guardian'], $accounting);
        app(PostPaymentAction::class)->execute($payment, $accounting);
        $period = ExamPeriod::query()->create(['academic_year_id' => $enrollment->academic_year_id, 'code' => 'OPEN', 'name' => 'Fictional Open Exam', 'starts_on' => today()->addDays(10), 'ends_on' => today()->addDays(11), 'eligibility_cutoff_on' => today(), 'issue_opens_on' => today()->subDay(), 'issue_closes_on' => today()->addDay(), 'status' => 'open']);
        $firstPermit = app(IssueExamPermitAction::class)->execute($assessment, $period, $accounting);
        app(RevokeExamPermitAction::class)->execute($firstPermit, 'Fictional revocation', $admin);
        $replacement = app(IssueExamPermitAction::class)->execute($assessment, $period, $accounting);
        $this->assertNotSame($firstPermit->id, $replacement->id);
        $this->assertSame($firstPermit->id, $replacement->replaces_permit_id);
        $this->assertNull($firstPermit->fresh()->active_permit_key);
        $this->assertNotNull($replacement->active_permit_key);

        $override = ExamPermitOverride::query()->create(['enrollment_id' => $enrollment->id, 'exam_period_id' => $period->id, 'reason_category' => 'promissory_note', 'confidential_reason' => 'Fictional restricted note', 'supporting_document_reference' => 'FIC-OFFLINE-1', 'effective_from' => today(), 'expires_on' => today()->addMonth(), 'status' => 'requested', 'requested_by' => $accounting->id, 'verified_by' => $accounting->id, 'verified_at' => now()]);
        app(ApprovePermitOverrideAction::class)->execute($override, $admin, true);
        app(RevokePermitOverrideAction::class)->execute($override->fresh(), 'Fictional override revoked', $admin);
        $this->assertSame('revoked', $override->fresh()->status);
        $this->assertNotNull($override->fresh()->revoked_at);

        $closed = ExamPeriod::query()->create(['academic_year_id' => $enrollment->academic_year_id, 'code' => 'FUTURE', 'name' => 'Fictional Future Window', 'starts_on' => today()->addMonths(2), 'ends_on' => today()->addMonths(2)->addDay(), 'eligibility_cutoff_on' => today()->addMonth(), 'issue_opens_on' => today()->addMonth(), 'issue_closes_on' => today()->addMonths(2), 'status' => 'planned']);
        $this->expectException(ValidationException::class);
        app(IssueExamPermitAction::class)->execute($assessment, $closed, $accounting);
    }

    public function test_credit_allocation_is_append_only_and_cannot_cross_students(): void
    {
        $accounting = $this->user(['accounting']);
        [$enrollment, $schedule] = $this->fixture();
        $assessment = app(CreateAssessmentAction::class)->execute($enrollment, $schedule, [], $accounting);
        app(FinalizeAssessmentAction::class)->execute($assessment, $accounting);
        // Payments may not exceed the balance; credit arises from an approved correction after full payment.
        $payment = Payment::query()->create(['fee_assessment_id' => $assessment->id, 'received_on' => today(), 'amount' => '28500.00', 'method' => 'cash', 'payer_name' => 'Fictional Guardian', 'status' => 'draft', 'created_by' => $accounting->id]);
        PaymentAllocation::query()->create(['payment_id' => $payment->id, 'amount' => '28500.00']);
        app(PostPaymentAction::class)->execute($payment, $accounting);
        $correction = app(RequestAdjustmentAction::class)->execute($assessment, ['adjustment_type' => 'credit', 'amount' => '1500.00', 'reason' => 'Fictional approved correction'], $accounting);
        app(ApproveAdjustmentAction::class)->execute($correction, $this->user(['admin']), true);
        $target = $assessment->items()->where('fee_code', 'TUI')->firstOrFail();
        $credit = app(AllocateStudentCreditAction::class)->execute($payment, $target, '500.00', 'Fictional same-student allocation', $accounting);
        $this->assertSame('500.00', (string) $credit->amount);
        try {
            $credit->update(['amount' => '1.00']);
            $this->fail('Credit allocations must be append-only.');
        } catch (\LogicException) {
            $this->assertTrue(true);
        }

        $otherEnrollment = $this->additionalEnrollment($enrollment, 'OTHER', now()->addMinute());
        $otherAssessment = app(CreateAssessmentAction::class)->execute($otherEnrollment, $schedule, [], $accounting);
        app(FinalizeAssessmentAction::class)->execute($otherAssessment, $accounting);
        $this->expectException(ValidationException::class);
        app(AllocateStudentCreditAction::class)->execute($payment, $otherAssessment->items()->firstOrFail(), '500.00', 'Forbidden cross-student allocation', $accounting);
    }

    private function fixture(): array
    {
        $year = AcademicYear::query()->create(['code' => 'LIFE-Y', 'name' => 'Fictional life year', 'starts_on' => '2026-06-01', 'ends_on' => '2027-03-31', 'status' => 'open']);
        $grade = GradeLevel::query()->create(['code' => 'LIFE-G', 'name' => 'Fictional Grade', 'sequence' => 1, 'is_active' => true]);
        $student = StudentProfile::query()->create(['first_name' => 'Fictional', 'last_name' => 'Student']);
        $guardian = GuardianProfile::query()->create(['first_name' => 'Fictional', 'last_name' => 'Guardian']);
        $enrollment = Enrollment::query()->create(['application_number' => 'LIFE-'.Str::upper(Str::random(6)), 'student_id' => $student->id, 'academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'applicant_guardian_id' => $guardian->id, 'student_category' => 'regular', 'status' => 'approved', 'approved_at' => now(), 'active_enrollment_key' => $student->id.':'.$year->id]);
        $tuition = FeeType::query()->create(['code' => 'TUI', 'name' => 'Tuition Fee', 'category' => 'Tuition', 'is_mandatory' => true, 'is_permit_critical' => true]);
        $misc = FeeType::query()->create(['code' => 'MSC', 'name' => 'Miscellaneous Fee', 'category' => 'School Fee', 'is_mandatory' => true, 'is_permit_critical' => true]);
        $booksType = FeeType::query()->create(['code' => 'BKS', 'name' => 'Books', 'category' => 'Learning Materials', 'is_conditional' => true]);
        $schedule = FeeSchedule::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Fictional published schedule', 'status' => 'published']);
        FeeScheduleItem::query()->create(['fee_schedule_id' => $schedule->id, 'fee_type_id' => $tuition->id, 'description' => 'Tuition', 'amount' => '24000.00', 'is_required' => true]);
        FeeScheduleItem::query()->create(['fee_schedule_id' => $schedule->id, 'fee_type_id' => $misc->id, 'description' => 'Miscellaneous', 'amount' => '4500.00', 'is_required' => true]);
        $books = FeeScheduleItem::query()->create(['fee_schedule_id' => $schedule->id, 'fee_type_id' => $booksType->id, 'description' => 'Books', 'amount' => '4500.00', 'is_required' => false]);

        return [$enrollment, $schedule, $books];
    }

    private function additionalEnrollment(Enrollment $source, string $suffix, $approvedAt): Enrollment
    {
        $student = StudentProfile::query()->create(['first_name' => 'Fictional', 'last_name' => $suffix]);
        $guardian = GuardianProfile::query()->create(['first_name' => 'Guardian', 'last_name' => $suffix]);

        return Enrollment::query()->create(['application_number' => 'LIFE-'.$suffix, 'student_id' => $student->id, 'academic_year_id' => $source->academic_year_id, 'grade_level_id' => $source->grade_level_id, 'applicant_guardian_id' => $guardian->id, 'student_category' => 'regular', 'status' => 'approved', 'approved_at' => $approvedAt, 'active_enrollment_key' => $student->id.':'.$source->academic_year_id]);
    }

    private function user(array $roles): User
    {
        $user = User::query()->create(['name' => 'Fictional '.Str::random(5), 'email' => Str::lower(Str::random(8)).'@example.test', 'password' => Hash::make('Password12345'), 'status' => AccountStatus::Active, 'password_changed_at' => now()]);

        return app(AssignRolesAction::class)->execute($user, $roles, null);
    }
}
