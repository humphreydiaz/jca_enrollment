<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Billing\Actions\ApproveAdjustmentAction;
use App\Modules\Billing\Actions\ApprovePaymentToFollowAction;
use App\Modules\Billing\Actions\ApprovePermitOverrideAction;
use App\Modules\Billing\Actions\ApproveRefundAction;
use App\Modules\Billing\Actions\ApproveReversalAction;
use App\Modules\Billing\Actions\AwardDiscountAction;
use App\Modules\Billing\Actions\CreateAssessmentAction;
use App\Modules\Billing\Actions\FinalizeAssessmentAction;
use App\Modules\Billing\Actions\IssueExamPermitAction;
use App\Modules\Billing\Actions\PostPaymentAction;
use App\Modules\Billing\Actions\RequestAdjustmentAction;
use App\Modules\Billing\Actions\RequestRefundAction;
use App\Modules\Billing\Actions\SelectPaymentPlanAction;
use App\Modules\Billing\Calculations\BalanceCalculator;
use App\Modules\Billing\Calculations\Money;
use App\Modules\Billing\Models\DiscountCampaign;
use App\Modules\Billing\Models\ExamPeriod;
use App\Modules\Billing\Models\ExamPermit;
use App\Modules\Billing\Models\ExamPermitCalculation;
use App\Modules\Billing\Models\ExamPermitOverride;
use App\Modules\Billing\Models\ExamPermitPrint;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Billing\Models\FeeScheduleItem;
use App\Modules\Billing\Models\FeeType;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentAllocation;
use App\Modules\Billing\Models\PaymentEvidence;
use App\Modules\Billing\Models\PaymentPlanOption;
use App\Modules\Billing\Models\PaymentReversal;
use App\Modules\Billing\Models\ReceiptPrint;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\StaffProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BillingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_assessment_snapshot_payment_reference_reversal_and_balance(): void
    {
        $accounting = $this->user(['accounting']);
        $admin = $this->user(['admin']);
        [$enrollment,$schedule] = $this->fixture();
        $assessment = app(CreateAssessmentAction::class)->execute($enrollment, $schedule, [], $accounting);
        app(FinalizeAssessmentAction::class)->execute($assessment, $accounting);
        $schedule->items()->with('feeType')->firstOrFail()->feeType->update(['name' => 'Updated Fictional Tuition']);
        $this->assertSame('28500.00', (string) $assessment->fresh()->net_total);
        $payment = Payment::query()->create(['fee_assessment_id' => $assessment->id, 'received_on' => '2026-06-01', 'amount' => '30000.00', 'method' => 'cash', 'payer_name' => 'Fictional Guardian', 'status' => 'draft', 'created_by' => $accounting->id]);
        PaymentAllocation::query()->create(['payment_id' => $payment->id, 'assessment_item_id' => $assessment->items()->first()->id, 'amount' => '30000.00']);
        app(PostPaymentAction::class)->execute($payment, $accounting);
        $this->assertMatchesRegularExpression('/^PAY-\d{4}-\d{6}$/', $payment->fresh()->payment_reference);
        $this->assertMatchesRegularExpression('/^JCA-REC-\d{4}-\d{6}$/', $payment->fresh()->receipt_reference);
        $reversal = PaymentReversal::query()->create(['payment_id' => $payment->id, 'amount' => '1000.00', 'reason' => 'Fictional correction', 'requested_by' => $accounting->id, 'requested_at' => now()]);
        app(ApproveReversalAction::class)->execute($reversal, $admin);
        $balance = app(BalanceCalculator::class)->calculate($assessment->fresh());
        $this->assertSame('0.00', $balance['due']);
        $this->assertSame('500.00', $balance['credit']);
        $tooMuch = PaymentReversal::query()->create(['payment_id' => $payment->id, 'amount' => '30000.00', 'reason' => 'Too much', 'requested_by' => $accounting->id, 'requested_at' => now()]);
        $this->expectException(ValidationException::class);
        app(ApproveReversalAction::class)->execute($tooMuch, $admin);
    }

    public function test_authorization_and_no_gateway_routes(): void
    {
        $accounting = $this->user(['accounting']);
        $registrar = $this->user(['registrar']);
        $guardianUser = $this->user(['guardian']);
        $teacher = $this->user(['teacher']);
        $admin = $this->user(['admin']);
        $adminAccounting = $this->user(['admin', 'accounting']);
        [$enrollment,$schedule] = $this->fixture($guardianUser);
        $assessment = app(CreateAssessmentAction::class)->execute($enrollment, $schedule, [], $accounting);
        app(FinalizeAssessmentAction::class)->execute($assessment, $accounting);
        $this->actingAs($accounting)->get(route('billing.index'))->assertOk();
        $this->actingAs($registrar)->get(route('billing.index'))->assertOk();
        $this->actingAs($teacher)->get(route('billing.index'))->assertForbidden();
        $this->actingAs($guardianUser)->get(route('billing.portal.show', $assessment))->assertOk();
        $draft = Payment::query()->create(['fee_assessment_id' => $assessment->id, 'received_on' => today(), 'amount' => '10.00', 'method' => 'cash', 'payer_name' => 'Fictional Payer', 'status' => 'draft', 'created_by' => $accounting->id]);
        PaymentAllocation::query()->create(['payment_id' => $draft->id, 'assessment_item_id' => $assessment->items()->first()->id, 'amount' => '10.00']);
        $this->actingAs($admin)->post(route('billing.payments.post', $draft))->assertForbidden();
        $this->actingAs($adminAccounting)->post(route('billing.payments.post', $draft))->assertRedirect();
        $this->assertSame('posted', $draft->fresh()->status);
        $this->get('/payment-gateway')->assertNotFound();
    }

    public function test_admin_can_view_the_awaiting_assessment_queue_without_receiving_accounting_authority(): void
    {
        $admin = $this->user(['admin']);
        $accounting = $this->user(['accounting']);
        $registrar = $this->user(['registrar']);
        [$enrollment, $schedule] = $this->fixture();

        $adminResponse = $this->actingAs($admin)->get(route('billing.index'));
        $adminResponse
            ->assertOk()
            ->assertSee('1. Students awaiting an assessment')
            ->assertSee('Fictional Student')
            ->assertSee('Ready for Accounting assessment')
            ->assertDontSee(route('billing.assessments.create', $enrollment), false);

        $this->actingAs($accounting)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertSee('Fictional Student')
            ->assertSee(route('billing.assessments.create', $enrollment), false);

        $this->actingAs($registrar)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertDontSee('Fictional Student');

        $this->actingAs($admin)
            ->post(route('billing.assessments.create', $enrollment), ['fee_schedule_id' => $schedule->id])
            ->assertForbidden();
        $this->assertDatabaseMissing('fee_assessments', ['enrollment_id' => $enrollment->id]);
    }

    public function test_payment_plan_permit_print_and_portal_contracts(): void
    {
        $accounting = $this->user(['accounting']);
        $admin = $this->user(['admin']);
        $registrar = $this->user(['registrar']);
        $guardian = $this->user(['guardian']);
        $studentUser = $this->user(['student']);
        $unrelatedStudent = $this->user(['student']);
        $unrelatedGuardian = $this->user(['guardian']);
        [$enrollment, $schedule] = $this->fixture($guardian, $studentUser);
        $assessment = app(CreateAssessmentAction::class)->execute($enrollment, $schedule, [], $accounting);
        app(FinalizeAssessmentAction::class)->execute($assessment, $accounting);

        foreach ([['Q1', 'First Quarter', '2026-08-20'], ['Q2', 'Second Quarter', '2026-10-22'], ['Q3', 'Third Quarter', '2027-01-21'], ['Q4', 'Fourth Quarter', '2027-03-18']] as [$code, $name, $start]) {
            ExamPeriod::query()->create(['academic_year_id' => $enrollment->academic_year_id, 'code' => $code, 'name' => $name, 'starts_on' => $start, 'ends_on' => $start, 'eligibility_cutoff_on' => date('Y-m-d', strtotime($start.' -10 days')), 'status' => 'planned']);
        }
        $plan = app(SelectPaymentPlanAction::class)->execute($assessment, 'monthly', $accounting);
        $this->assertCount(10, $plan->scheduleItems);
        $this->assertSame('28500.00', Money::sum($plan->scheduleItems->pluck('amount')));
        $this->assertSame('6900.00', (string) $plan->scheduleItems->first()->amount);

        $ordinaryFollow = app(ApprovePaymentToFollowAction::class)->execute($assessment, '2026-06-04', null, $accounting);
        $this->assertFalse($ordinaryFollow->is_extension);
        try {
            app(ApprovePaymentToFollowAction::class)->execute($assessment, '2026-06-20', 'Too long', $accounting);
            $this->fail('Accounting must not approve an extension beyond 15 days.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        $this->assertTrue(app(ApprovePaymentToFollowAction::class)->execute($assessment, '2026-06-20', 'Fictional approved extension', $admin)->is_extension);

        $payment = Payment::query()->create(['fee_assessment_id' => $assessment->id, 'received_on' => '2026-08-01', 'amount' => '28500.00', 'method' => 'cash', 'payer_name' => 'Fictional Guardian', 'status' => 'draft', 'created_by' => $accounting->id]);
        foreach ($assessment->items as $item) {
            PaymentAllocation::query()->create(['payment_id' => $payment->id, 'assessment_item_id' => $item->id, 'amount' => $item->net_amount]);
        }
        app(PostPaymentAction::class)->execute($payment, $accounting);
        $period = ExamPeriod::query()->where('code', 'Q1')->firstOrFail();
        $permit = app(IssueExamPermitAction::class)->execute($assessment->fresh(), $period, $accounting);
        $this->assertSame('issued', $permit->status);

        $this->actingAs($guardian)->get(route('billing.print.receipt', $payment->fresh()))->assertOk()->assertSee('ORIGINAL')->assertSee($payment->fresh()->receipt_reference)->assertSee('images/reporting/jca-reports-header.png');
        $this->actingAs($guardian)->get(route('billing.print.receipt', $payment->fresh()))->assertOk()->assertSee('REPRINT #1');
        $permitResponse = $this->actingAs($guardian)->get(route('billing.print.permit', $permit));
        $permitResponse->assertOk()->assertSee('AUTHORIZED')->assertSee('images/reporting/jca-reports-header.png')->assertDontSee('outstanding balance')->assertDontSee('hardship')->assertDontSee('payment history');
        $this->assertSame(2, ReceiptPrint::query()->where('payment_id', $payment->id)->count());
        $this->assertSame(1, ExamPermitPrint::query()->where('exam_permit_id', $permit->id)->count());

        $this->actingAs($registrar)->get(route('billing.portal.show', $assessment))->assertOk()->assertSee('Financial status')->assertDontSee('Tuition Fee');
        $this->actingAs($registrar)->get(route('billing.print.receipt', $payment->fresh()))->assertForbidden();
        $this->actingAs($studentUser)->get(route('billing.portal.show', $assessment))->assertOk();
        $this->actingAs($studentUser)->get(route('billing.portal.index'))->assertOk()->assertSee('Fictional Student');
        $this->actingAs($unrelatedGuardian)->get(route('billing.portal.show', $assessment))->assertNotFound();
        $this->actingAs($unrelatedGuardian)->get(route('billing.portal.index'))->assertOk()->assertDontSee($assessment->assessment_reference);
        $this->actingAs($unrelatedStudent)->get(route('billing.portal.show', $assessment))->assertNotFound();
        $this->actingAs($unrelatedGuardian)->get('/finance/assessments/'.Str::ulid())->assertNotFound();
        auth()->logout();
        $this->get(route('billing.portal.show', $assessment))->assertRedirect();

        $this->expectException(\LogicException::class);
        $payment->fresh()->update(['amount' => '1.00']);
    }

    public function test_discount_limit_and_assessment_revision_history_are_preserved(): void
    {
        $accounting = $this->user(['accounting']);
        [$firstEnrollment, $schedule] = $this->fixture();
        $campaign = DiscountCampaign::query()->create(['academic_year_id' => $firstEnrollment->academic_year_id, 'code' => 'FIRST', 'name' => 'Fictional early award', 'value_type' => 'fixed', 'value_amount' => '1000.00', 'slot_limit' => 1, 'starts_on' => today()->subDay(), 'ends_on' => today()->addDay(), 'is_active' => true, 'is_development_fixture' => true]);
        $firstAward = app(AwardDiscountAction::class)->execute($campaign, $firstEnrollment, $accounting);
        $this->assertSame(1, $firstAward?->rank);

        $secondStudent = StudentProfile::query()->create(['first_name' => 'Second', 'last_name' => 'Fictional']);
        $secondGuardian = GuardianProfile::query()->create(['first_name' => 'Second', 'last_name' => 'Guardian']);
        $secondEnrollment = Enrollment::query()->create(['application_number' => 'APP-SECOND', 'student_id' => $secondStudent->id, 'academic_year_id' => $firstEnrollment->academic_year_id, 'grade_level_id' => $firstEnrollment->grade_level_id, 'applicant_guardian_id' => $secondGuardian->id, 'student_category' => 'returning', 'status' => 'approved', 'approved_at' => now(), 'active_enrollment_key' => $secondStudent->id.':'.$firstEnrollment->academic_year_id]);
        $this->assertNull(app(AwardDiscountAction::class)->execute($campaign, $secondEnrollment, $accounting));

        $first = app(CreateAssessmentAction::class)->execute($firstEnrollment, $schedule, [], $accounting);
        $this->assertSame('1000.00', (string) $first->discount_total);
        $this->assertSame('23000.00', (string) $first->items()->where('fee_code', 'TUI')->value('net_amount'));
        app(FinalizeAssessmentAction::class)->execute($first, $accounting);
        $revision = app(CreateAssessmentAction::class)->execute($firstEnrollment, $schedule, [], $accounting, 'Fictional correction before payment');
        $this->assertSame($first->id, $revision->previous_assessment_id);
        $this->assertSame(2, $revision->revision_number);
        app(FinalizeAssessmentAction::class)->execute($revision, $accounting);
        $this->assertSame('superseded', $first->fresh()->status);
        $this->assertSame(1, FeeAssessment::query()->where('enrollment_id', $firstEnrollment->id)->where('status', 'finalized')->count());

        $payment = Payment::query()->create(['fee_assessment_id' => $revision->id, 'received_on' => '2026-06-01', 'amount' => '100.00', 'method' => 'cash', 'payer_name' => 'Fictional Guardian', 'status' => 'draft', 'created_by' => $accounting->id]);
        PaymentAllocation::query()->create(['payment_id' => $payment->id, 'assessment_item_id' => $revision->items()->first()->id, 'amount' => '100.00']);
        app(PostPaymentAction::class)->execute($payment, $accounting);
        $this->expectException(ValidationException::class);
        app(CreateAssessmentAction::class)->execute($firstEnrollment, $schedule, [], $accounting, 'Must use adjustment');
    }

    public function test_admin_configuration_and_private_payment_evidence_authorization(): void
    {
        Storage::fake('documents');
        $accounting = $this->user(['accounting']);
        $admin = $this->user(['admin']);
        $guardian = $this->user(['guardian']);
        [$enrollment, $schedule] = $this->fixture($guardian);
        $assessment = app(CreateAssessmentAction::class)->execute($enrollment, $schedule, [], $accounting);
        app(FinalizeAssessmentAction::class)->execute($assessment, $accounting);
        $payment = Payment::query()->create(['fee_assessment_id' => $assessment->id, 'received_on' => '2026-06-01', 'amount' => '100.00', 'method' => 'bank_transfer', 'payer_name' => 'Fictional Guardian', 'method_reference' => 'FICTIONAL-REF', 'status' => 'draft', 'created_by' => $accounting->id]);

        $this->actingAs($admin)->get(route('billing.configuration.index'))->assertOk()->assertSee('Financial configuration');
        $this->actingAs($accounting)->get(route('billing.configuration.index'))->assertForbidden();
        $this->actingAs($accounting)->post(route('billing.evidence.store', $payment), ['evidence' => UploadedFile::fake()->create('fictional-proof.pdf', 10, 'application/pdf')])->assertRedirect();
        $evidence = PaymentEvidence::query()->firstOrFail();
        Storage::disk('documents')->assertExists($evidence->storage_path);
        $this->assertStringNotContainsString('fictional-proof', $evidence->storage_path);
        $this->actingAs($guardian)->get(route('billing.evidence.download', $evidence))->assertForbidden();
        $this->actingAs($admin)->get(route('billing.evidence.download', $evidence))->assertOk()->assertHeader('x-content-type-options', 'nosniff');
        auth()->logout();
        $this->get('/storage/'.$evidence->storage_path)->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'billing.payment_evidence.downloaded', 'subject_id' => $evidence->id]);
    }

    public function test_permit_eligibility_override_and_reversal_are_reproducible(): void
    {
        $accounting = $this->user(['accounting']);
        $admin = $this->user(['admin']);
        [$enrollment, $schedule] = $this->fixture();
        $assessment = app(CreateAssessmentAction::class)->execute($enrollment, $schedule, [], $accounting);
        app(FinalizeAssessmentAction::class)->execute($assessment, $accounting);
        app(SelectPaymentPlanAction::class)->execute($assessment, 'full', $accounting);
        app(ApprovePaymentToFollowAction::class)->execute($assessment, '2026-06-04', null, $accounting);
        $periodOne = ExamPeriod::query()->create(['academic_year_id' => $enrollment->academic_year_id, 'code' => 'Q1', 'name' => 'First Quarter', 'starts_on' => '2026-08-20', 'ends_on' => '2026-08-21', 'eligibility_cutoff_on' => '2026-08-10', 'status' => 'planned']);
        try {
            app(IssueExamPermitAction::class)->execute($assessment, $periodOne, $accounting);
            $this->fail('Payment-to-follow must not make an unpaid assessment eligible.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('exam_permit_calculations', ['fee_assessment_id' => $assessment->id, 'ordinary_eligible' => false]);
        }

        $override = ExamPermitOverride::query()->create(['enrollment_id' => $enrollment->id, 'exam_period_id' => $periodOne->id, 'reason_category' => 'financial_hardship', 'confidential_reason' => 'Fictional private reason', 'effective_from' => today(), 'expires_on' => today()->addMonth(), 'status' => 'requested', 'requested_by' => $accounting->id]);
        app(ApprovePermitOverrideAction::class)->execute($override, $admin, true);
        $overridePermit = app(IssueExamPermitAction::class)->execute($assessment, $periodOne, $accounting);
        $this->assertSame($override->id, $overridePermit->override_id);

        $payment = Payment::query()->create(['fee_assessment_id' => $assessment->id, 'received_on' => '2026-08-01', 'amount' => '28500.00', 'method' => 'cash', 'payer_name' => 'Fictional Guardian', 'status' => 'draft', 'created_by' => $accounting->id]);
        foreach ($assessment->items as $item) {
            PaymentAllocation::query()->create(['payment_id' => $payment->id, 'assessment_item_id' => $item->id, 'amount' => $item->net_amount]);
        }
        app(PostPaymentAction::class)->execute($payment, $accounting);
        $periodTwo = ExamPeriod::query()->create(['academic_year_id' => $enrollment->academic_year_id, 'code' => 'Q2', 'name' => 'Second Quarter', 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-21', 'eligibility_cutoff_on' => '2026-10-10', 'status' => 'planned']);
        $this->assertNull(app(IssueExamPermitAction::class)->execute($assessment, $periodTwo, $accounting)->override_id);
        $reversal = PaymentReversal::query()->create(['payment_id' => $payment->id, 'amount' => '1000.00', 'reason' => 'Fictional returned collection', 'requested_by' => $accounting->id, 'requested_at' => now()]);
        app(ApproveReversalAction::class)->execute($reversal, $admin);
        $periodThree = ExamPeriod::query()->create(['academic_year_id' => $enrollment->academic_year_id, 'code' => 'Q3', 'name' => 'Third Quarter', 'starts_on' => '2027-01-20', 'ends_on' => '2027-01-21', 'eligibility_cutoff_on' => '2027-01-10', 'status' => 'planned']);
        try {
            app(IssueExamPermitAction::class)->execute($assessment, $periodThree, $accounting);
            $this->fail('An approved reversal reducing coverage must change eligibility.');
        } catch (ValidationException) {
            $calculation = ExamPermitCalculation::query()->where('exam_period_id', $periodThree->id)->latest()->firstOrFail();
            $this->assertFalse($calculation->ordinary_eligible);
            $this->assertSame('27500.00', (string) $calculation->satisfied_amount);
        }
    }

    public function test_teacher_sees_only_assigned_exam_authorization_status(): void
    {
        $accounting = $this->user(['accounting']);
        $teacher = $this->user(['teacher']);
        $unassignedTeacher = $this->user(['teacher']);
        [$enrollment, $schedule] = $this->fixture();
        $assessment = app(CreateAssessmentAction::class)->execute($enrollment, $schedule, [], $accounting);
        app(FinalizeAssessmentAction::class)->execute($assessment, $accounting);
        $period = ExamPeriod::query()->create(['academic_year_id' => $enrollment->academic_year_id, 'code' => 'TEACH-Q', 'name' => 'Fictional Exam', 'starts_on' => today()->addMonth(), 'ends_on' => today()->addMonth()->addDay(), 'eligibility_cutoff_on' => today()->addWeeks(2), 'status' => 'planned']);
        $permit = ExamPermit::query()->create(['permit_reference' => 'JCA-PERMIT-2026-999999', 'enrollment_id' => $enrollment->id, 'exam_period_id' => $period->id, 'fee_assessment_id' => $assessment->id, 'required_amount' => '0.00', 'satisfied_amount' => '0.00', 'calculated_at' => now(), 'issued_at' => now(), 'issued_by' => $accounting->id, 'status' => 'issued']);
        $section = Section::query()->create(['academic_year_id' => $enrollment->academic_year_id, 'grade_level_id' => $enrollment->grade_level_id, 'name' => 'Fictional Section', 'capacity' => 15, 'status' => 'active']);
        SectionAssignment::query()->create(['enrollment_id' => $enrollment->id, 'section_id' => $section->id, 'status' => 'confirmed', 'active_assignment_key' => $enrollment->id, 'confirmed_by' => $accounting->id, 'confirmed_at' => now(), 'effective_from' => today()]);
        $staff = StaffProfile::query()->create(['user_id' => $teacher->id, 'employee_number' => 'FIC-T1', 'position_title' => 'Teacher', 'active_from' => today()->subYear()]);
        StaffProfile::query()->create(['user_id' => $unassignedTeacher->id, 'employee_number' => 'FIC-T2', 'position_title' => 'Teacher', 'active_from' => today()->subYear()]);
        TeachingAssignment::query()->create(['staff_profile_id' => $staff->id, 'section_id' => $section->id, 'assignment_type' => 'adviser', 'active_from' => today()->subMonth(), 'is_active' => true]);

        $response = $this->actingAs($teacher)->get(route('billing.teacher.status', $enrollment));
        $response->assertOk()->assertSee('Authorized for exam')->assertSee($permit->permit_reference)->assertDontSee('PHP')->assertDontSee('balance')->assertDontSee('Tuition');
        $this->actingAs($teacher)->get(route('billing.portal.show', $assessment))->assertForbidden();
        $this->actingAs($unassignedTeacher)->get(route('billing.teacher.status', $enrollment))->assertNotFound();
    }

    public function test_offline_method_validation_credit_refund_and_adjustments(): void
    {
        $accounting = $this->user(['accounting']);
        $admin = $this->user(['admin']);
        [$enrollment, $schedule] = $this->fixture();
        $assessment = app(CreateAssessmentAction::class)->execute($enrollment, $schedule, [], $accounting);
        app(FinalizeAssessmentAction::class)->execute($assessment, $accounting);

        $this->actingAs($accounting)->post(route('billing.payments.create', $assessment), ['received_on' => '2026-06-01', 'amount' => '100.00', 'method' => 'cheque', 'payer_name' => 'Fictional Payer'])->assertSessionHasErrors(['method_reference', 'bank_name']);
        $this->actingAs($accounting)->post(route('billing.payments.create', $assessment), ['received_on' => '2026-06-01', 'amount' => '100.00', 'method' => 'cash', 'payer_name' => 'Fictional Payer', 'card_number' => '4111111111111111'])->assertSessionHasErrors('card_number');

        $payment = Payment::query()->create(['fee_assessment_id' => $assessment->id, 'received_on' => '2026-06-01', 'amount' => '30000.00', 'method' => 'cash', 'payer_name' => 'Fictional Guardian', 'status' => 'draft', 'created_by' => $accounting->id]);
        foreach ($assessment->items as $item) {
            PaymentAllocation::query()->create(['payment_id' => $payment->id, 'assessment_item_id' => $item->id, 'amount' => $item->net_amount]);
        }
        app(PostPaymentAction::class)->execute($payment, $accounting);
        $this->assertSame('1500.00', app(BalanceCalculator::class)->calculate($assessment)['credit']);
        $refund = app(RequestRefundAction::class)->execute($assessment, '1000.00', 'Fictional written request', $accounting);
        app(ApproveRefundAction::class)->execute($refund, $admin, true, 'FIC-RELEASE-1', '2026-06-02');
        $this->assertSame('500.00', app(BalanceCalculator::class)->calculate($assessment)['credit']);
        $credit = app(RequestAdjustmentAction::class)->execute($assessment, ['adjustment_type' => 'credit', 'amount' => '500.00', 'reason' => 'Fictional approved correction'], $accounting);
        app(ApproveAdjustmentAction::class)->execute($credit, $admin, true);
        $this->assertSame('1000.00', app(BalanceCalculator::class)->calculate($assessment)['credit']);
        $debit = app(RequestAdjustmentAction::class)->execute($assessment, ['adjustment_type' => 'debit', 'amount' => '500.00', 'reason' => 'Fictional debit correction'], $accounting);
        app(ApproveAdjustmentAction::class)->execute($debit, $admin, true);
        $this->assertSame('500.00', app(BalanceCalculator::class)->calculate($assessment)['credit']);
        $this->assertDatabaseHas('refunds', ['id' => $refund->id, 'status' => 'approved', 'release_reference' => 'FIC-RELEASE-1']);
    }

    public function test_payment_plan_configuration_prevents_duplicates_and_uses_safe_deactivation(): void
    {
        $admin = $this->user(['admin']);
        $accounting = $this->user(['accounting']);
        [$enrollment] = $this->fixture();
        $data = [
            'academic_year_id' => $enrollment->academic_year_id,
            'grade_level_id' => $enrollment->grade_level_id,
            'code' => 'monthly',
            'name' => 'Fictional Grade 1 Monthly',
            'is_active' => '1',
        ];

        $this->actingAs($admin)->post(route('billing.configuration.plan-options'), $data)->assertRedirect();
        $this->actingAs($admin)->post(route('billing.configuration.plan-options'), $data)->assertSessionHasErrors('code');
        $this->assertSame(1, PaymentPlanOption::query()->where('academic_year_id', $enrollment->academic_year_id)->where('grade_level_id', $enrollment->grade_level_id)->where('code', 'monthly')->count());

        $allGrades = [...$data, 'grade_level_id' => null, 'code' => 'full', 'name' => 'Fictional All Grades Full'];
        $this->actingAs($admin)->post(route('billing.configuration.plan-options'), $allGrades)->assertRedirect();
        $this->actingAs($admin)->post(route('billing.configuration.plan-options'), $allGrades)->assertSessionHasErrors('code');
        $this->assertSame(1, PaymentPlanOption::query()->where('academic_year_id', $enrollment->academic_year_id)->whereNull('grade_level_id')->where('code', 'full')->count());

        $option = PaymentPlanOption::query()->where('grade_level_id', $enrollment->grade_level_id)->firstOrFail();
        $this->actingAs($accounting)->patch(route('billing.configuration.plan-options.status', $option), ['is_active' => '0', 'reason' => 'Fictional request'])->assertForbidden();
        $this->actingAs($admin)->patch(route('billing.configuration.plan-options.status', $option), ['is_active' => '0', 'reason' => 'Fictional configuration correction'])->assertRedirect();
        $this->assertFalse($option->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'billing.payment_plan_option.deactivated', 'subject_id' => $option->id]);
    }

    public function test_accounting_search_explains_missing_fee_schedule_and_only_shows_valid_plans(): void
    {
        $accounting = $this->user(['accounting']);
        [$enrollment, $schedule] = $this->fixture();
        $schedule->update(['status' => 'draft']);

        $this->actingAs($accounting)
            ->get(route('billing.index', ['search' => 'Fictional Student']))
            ->assertOk()
            ->assertSee('Fictional Student')
            ->assertSee('Blocked: no published fee schedule')
            ->assertSee('A payment plan by itself is not enough.');

        $schedule->update(['status' => 'published']);
        $assessment = app(CreateAssessmentAction::class)->execute($enrollment, $schedule, [], $accounting);
        app(FinalizeAssessmentAction::class)->execute($assessment, $accounting);
        PaymentPlanOption::query()->create(['academic_year_id' => $enrollment->academic_year_id, 'grade_level_id' => $enrollment->grade_level_id, 'code' => 'monthly', 'name' => 'Fictional Grade 1 Monthly', 'is_active' => true]);
        PaymentPlanOption::query()->create(['academic_year_id' => $enrollment->academic_year_id, 'grade_level_id' => $enrollment->grade_level_id, 'code' => 'full', 'name' => 'Fictional Grade 1 Full', 'is_active' => false]);

        $this->actingAs($accounting)
            ->get(route('billing.portal.show', $assessment))
            ->assertOk()
            ->assertSee('Fictional Grade 1 Monthly')
            ->assertDontSee('Fictional Grade 1 Full');
    }

    public function test_optional_fee_items_selected_by_string_ids_are_saved_to_draft_assessment(): void
    {
        $accounting = $this->user(['accounting']);
        [$enrollment, $schedule] = $this->fixture();
        $books = FeeType::query()->create(['code' => 'BKS', 'name' => 'Books', 'category' => 'Materials', 'is_mandatory' => false, 'is_permit_critical' => false]);
        $booksItem = FeeScheduleItem::query()->create(['fee_schedule_id' => $schedule->id, 'fee_type_id' => $books->id, 'description' => 'Books', 'amount' => '5500.00', 'is_required' => false]);

        $assessment = app(CreateAssessmentAction::class)->execute($enrollment, $schedule, [], $accounting);
        $this->assertFalse($assessment->items()->where('fee_code', 'BKS')->exists());

        // The browser submits checkbox values as strings; the action must still match them.
        $this->actingAs($accounting)
            ->put(route('billing.assessments.update', $assessment), [
                'fee_schedule_id' => (string) $schedule->id,
                'selected_items' => [(string) $booksItem->id],
                'revision_reason' => 'Add books',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertTrue($assessment->fresh()->items()->where('fee_code', 'BKS')->exists());
    }

    public function test_cancelled_assessment_reopens_re_assessment_and_hides_plan_actions(): void
    {
        $accounting = $this->user(['accounting']);
        [$enrollment, $schedule] = $this->fixture();

        // Create a draft assessment — the student leaves the awaiting queue.
        $this->actingAs($accounting)->post(route('billing.assessments.create', $enrollment), ['fee_schedule_id' => $schedule->id])->assertRedirect();
        $assessment = FeeAssessment::query()->where('enrollment_id', $enrollment->id)->firstOrFail();
        $awaitingWithDraft = $this->actingAs($accounting)->get(route('billing.index'))->viewData('assessableEnrollments')->getCollection()->pluck('id');
        $this->assertFalse($awaitingWithDraft->contains($enrollment->id));

        // Cancel the draft — the student returns to the awaiting queue (Fix 1).
        $this->actingAs($accounting)->post(route('billing.assessments.cancel', $assessment), ['reason' => 'Fictional wrong schedule'])->assertRedirect();
        $this->assertSame('cancelled', $assessment->fresh()->status);
        $awaitingAfterCancel = $this->actingAs($accounting)->get(route('billing.index'))->viewData('assessableEnrollments')->getCollection()->pluck('id');
        $this->assertTrue($awaitingAfterCancel->contains($enrollment->id));

        // The billing queue no longer offers "Choose plan" on the cancelled row (Fix 2).
        $this->actingAs($accounting)->get(route('billing.index'))->assertSee('Re-assess in Step 1');

        // The portal hides plan/payment actions for a cancelled assessment (Fix 3).
        $this->actingAs($accounting)->get(route('billing.portal.show', $assessment->fresh()))
            ->assertSee('can no longer take a payment plan')
            ->assertDontSee('Step 3: Choose payment plan');

        // Re-assessment is possible again and produces a fresh draft revision.
        $this->actingAs($accounting)->post(route('billing.assessments.create', $enrollment), ['fee_schedule_id' => $schedule->id])->assertRedirect();
        $this->assertSame(1, FeeAssessment::query()->where('enrollment_id', $enrollment->id)->where('status', 'draft')->count());
    }

    public function test_configuration_forms_answer_ajax_with_section_json_and_validation_json(): void
    {
        $admin = $this->user(['admin']);
        [$enrollment] = $this->fixture();

        $payload = [
            'academic_year_id' => $enrollment->academic_year_id,
            'grade_level_id' => $enrollment->grade_level_id,
            'code' => 'monthly',
            'name' => 'Fictional AJAX Monthly',
            'is_active' => '1',
        ];

        // A JSON (AJAX) request gets the re-rendered section back, not a redirect.
        $ok = $this->actingAs($admin)->postJson(route('billing.configuration.plan-options'), $payload);
        $ok->assertOk()
            ->assertJsonPath('section', 'payment-plans')
            ->assertJsonStructure(['section', 'html', 'message']);
        $this->assertStringContainsString('data-section="payment-plans"', $ok->json('html'));
        $this->assertStringContainsString('Fictional AJAX Monthly', $ok->json('html'));

        // A JSON request that fails validation gets 422 JSON, not an HTML redirect.
        $this->actingAs($admin)
            ->postJson(route('billing.configuration.plan-options'), [...$payload, 'name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        // A normal (non-JSON) request still redirects with a flash status.
        $this->actingAs($admin)
            ->post(route('billing.configuration.fee-types'), ['code' => 'LAB', 'name' => 'Laboratory', 'category' => 'Academic'])
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    private function fixture(?User $guardianUser = null, ?User $studentUser = null): array
    {
        $year = AcademicYear::query()->create(['code' => '2026', 'name' => 'SY 2026', 'starts_on' => '2026-06-01', 'ends_on' => '2027-03-31', 'status' => 'open']);
        $grade = GradeLevel::query()->create(['code' => 'G1', 'name' => 'Grade 1', 'sequence' => 1, 'is_active' => true]);
        $student = StudentProfile::query()->create(['user_id' => $studentUser?->id, 'first_name' => 'Fictional', 'last_name' => 'Student']);
        $guardian = GuardianProfile::query()->create(['user_id' => $guardianUser?->id, 'first_name' => 'Fictional', 'last_name' => 'Guardian']);
        if ($guardianUser) {
            GuardianStudent::query()->create(['guardian_id' => $guardian->id, 'student_id' => $student->id, 'relationship_type' => 'Parent', 'may_view_financials' => true, 'verified_at' => now()]);
        } $enrollment = Enrollment::query()->create(['application_number' => 'APP-'.Str::random(8), 'student_id' => $student->id, 'academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'applicant_guardian_id' => $guardian->id, 'student_category' => 'regular', 'status' => 'approved', 'approved_at' => '2026-05-20 09:00:00', 'active_enrollment_key' => $student->id.':'.$year->id]);
        $tuition = FeeType::query()->create(['code' => 'TUI', 'name' => 'Tuition Fee', 'category' => 'Tuition', 'is_mandatory' => true, 'is_permit_critical' => true]);
        $misc = FeeType::query()->create(['code' => 'MSC', 'name' => 'Miscellaneous Fee', 'category' => 'School Fee', 'is_mandatory' => true, 'is_permit_critical' => true]);
        $schedule = FeeSchedule::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Development Grade 1', 'status' => 'published']);
        FeeScheduleItem::query()->create(['fee_schedule_id' => $schedule->id, 'fee_type_id' => $tuition->id, 'description' => 'Tuition', 'amount' => '24000.00', 'is_required' => true]);
        FeeScheduleItem::query()->create(['fee_schedule_id' => $schedule->id, 'fee_type_id' => $misc->id, 'description' => 'Miscellaneous', 'amount' => '4500.00', 'is_required' => true]);

        return [$enrollment, $schedule];
    }

    private function user(array $roles): User
    {
        $u = User::query()->create(['name' => 'User '.Str::random(5), 'email' => Str::random(8).'@example.test', 'password' => Hash::make('Password12345'), 'status' => AccountStatus::Active, 'password_changed_at' => now()]);

        return app(AssignRolesAction::class)->execute($u, $roles, null);
    }
}
