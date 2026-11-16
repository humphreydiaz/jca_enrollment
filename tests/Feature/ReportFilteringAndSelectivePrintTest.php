<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Billing\Models\Payment;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\StaffProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Reporting\Actions\RequestReportExportAction;
use App\Modules\Reporting\Jobs\GenerateReportExport;
use App\Modules\Reporting\Reports\ReportCatalog;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use App\Modules\Shared\Audit\AuditRecorder;
use App\Modules\Shared\Audit\Models\AuditLog;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Pre-final panel: report filters, picking columns, and printing only the chosen rows. */
class ReportFilteringAndSelectivePrintTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;

    private GradeLevel $grade;

    private Section $faith;

    private Section $hope;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = $this->user(['admin']);
        $this->year = AcademicYear::query()->create(['code' => 'RPT-'.Str::random(6), 'name' => 'Report Year 2026-2027', 'starts_on' => '2026-06-01', 'ends_on' => '2027-03-31', 'status' => 'open']);
        $this->grade = GradeLevel::query()->create(['code' => 'RPT-G'.Str::random(4), 'name' => 'Grade 7', 'sequence' => random_int(100, 60000), 'is_active' => true]);
        $this->faith = Section::query()->create(['academic_year_id' => $this->year->id, 'grade_level_id' => $this->grade->id, 'name' => 'Faith', 'capacity' => 30, 'status' => 'active']);
        $this->hope = Section::query()->create(['academic_year_id' => $this->year->id, 'grade_level_id' => $this->grade->id, 'name' => 'Hope', 'capacity' => 30, 'status' => 'active']);
    }

    public function test_accounting_filters_payments_by_section_and_learner_and_picks_a_status_from_a_list(): void
    {
        $accounting = $this->user(['accounting']);
        $ana = $this->paidLearner('Ana', 'Reyes', $this->faith, 'PAY-FAITH-1', '1500.00');
        $ben = $this->paidLearner('Ben', 'Cruz', $this->hope, 'PAY-HOPE-1', '2500.00');

        $this->actingAs($accounting)->get(route('reporting.show', ['payments', 'section' => $this->faith->public_id]))
            ->assertOk()->assertSee('PAY-FAITH-1')->assertDontSee('PAY-HOPE-1')
            ->assertSee('<option value="posted"', false);

        $this->actingAs($accounting)->get(route('reporting.show', ['payments', 'search' => 'cruz']))
            ->assertOk()->assertSee('PAY-HOPE-1')->assertDontSee('PAY-FAITH-1');
        $this->actingAs($accounting)->get(route('reporting.show', ['payments', 'search' => $ana->student->student_number]))
            ->assertOk()->assertSee('PAY-FAITH-1')->assertDontSee('PAY-HOPE-1');

        // Sorting by any listed column, high to low.
        $this->actingAs($accounting)->get(route('reporting.show', ['payments', 'sort' => 'amount', 'direction' => 'desc']))
            ->assertOk()->assertSeeInOrder(['PAY-HOPE-1', 'PAY-FAITH-1']);
        $this->assertNotNull($ben);
    }

    public function test_only_the_picked_columns_are_shown_printed_and_exported(): void
    {
        Storage::fake('reports');
        Queue::fake();
        $accounting = $this->user(['accounting']);
        $this->paidLearner('Ana', 'Reyes', $this->faith, 'PAY-COLS-1', '1500.00');
        $picked = ['student_name', 'section', 'amount'];

        $this->actingAs($accounting)->get(route('reporting.show', ['payments', 'cols' => $picked]))
            ->assertOk()->assertSee('Reyes, Ana')->assertSee('1,500.00')->assertDontSee('PAY-COLS-1');

        $print = $this->actingAs($accounting)->get(route('reporting.print', ['payments', 'cols' => $picked]));
        $print->assertOk()->assertSee('Reyes, Ana')->assertSee('Faith')->assertDontSee('PAY-COLS-1')->assertDontSee('>Method<', false)
            ->assertSee('Prepared by');
        $audit = AuditLog::query()->where('action', 'reporting.report.printed')->latest('id')->firstOrFail();
        $this->assertSame($picked, $audit->metadata['fields']);

        $export = app(RequestReportExportAction::class)->execute('payments', [], $accounting, $picked);
        (new GenerateReportExport($export->id))->handle(app(ReportCatalog::class), app(AuditRecorder::class));
        $csv = Storage::disk('reports')->get($export->fresh()->storage_path);
        $this->assertStringContainsString('Student,Section,Amount', $csv);
        $this->assertStringNotContainsString('PAY-COLS-1', $csv);

        // A column outside the allowlist is refused, not silently printed.
        $this->actingAs($accounting)->get(route('reporting.print', ['payments', 'cols' => ['payer_name']]))
            ->assertRedirect()->assertSessionHasErrors('cols.0');
    }

    public function test_print_selected_rows_prints_only_those_rows_with_a_money_total(): void
    {
        $accounting = $this->user(['accounting']);
        $first = $this->paidLearner('Ana', 'Reyes', $this->faith, 'PAY-SEL-1', '1500.00');
        $second = $this->paidLearner('Ben', 'Cruz', $this->hope, 'PAY-SEL-2', '2500.00');
        $this->paidLearner('Cara', 'Diaz', $this->hope, 'PAY-SEL-3', '9999.00');
        $keys = Payment::query()->whereIn('payment_reference', ['PAY-SEL-1', 'PAY-SEL-2'])->pluck('public_id')->all();

        $this->actingAs($accounting)->get(route('reporting.show', 'payments'))
            ->assertOk()->assertSee('value="'.$keys[0].'"', false)->assertSee('Print selected rows');

        $this->actingAs($accounting)->get(route('reporting.print', ['payments', 'rows' => $keys]))
            ->assertOk()->assertSee('PAY-SEL-1')->assertSee('PAY-SEL-2')->assertDontSee('PAY-SEL-3')
            ->assertSee('rows selected on screen')->assertSee('4,000.00');

        // Ticked rows still go through the filters: a key outside them prints nothing extra.
        $this->actingAs($accounting)->get(route('reporting.print', ['payments', 'rows' => $keys, 'section' => $this->faith->public_id]))
            ->assertOk()->assertSee('PAY-SEL-1')->assertDontSee('PAY-SEL-2');

        // Keys must be opaque public references.
        $this->actingAs($accounting)->get(route('reporting.print', ['payments', 'rows' => ['1 OR 1=1']]))
            ->assertRedirect()->assertSessionHasErrors('rows.0');
        $this->assertNotNull($first);
        $this->assertNotNull($second);
    }

    public function test_a_teacher_cannot_print_learners_outside_assigned_sections_by_ticking_their_keys(): void
    {
        $teacher = $this->user(['teacher']);
        $staff = StaffProfile::query()->create(['user_id' => $teacher->id]);
        TeachingAssignment::query()->create(['staff_profile_id' => $staff->id, 'section_id' => $this->faith->id, 'assignment_type' => 'adviser', 'is_active' => true]);
        $mine = $this->enrolledLearner('Mine', 'Learner', $this->faith);
        $other = $this->enrolledLearner('Other', 'Learner', $this->hope);

        $this->actingAs($teacher)->get(route('reporting.print', ['students', 'rows' => [$mine->public_id, $other->public_id]]))
            ->assertOk()->assertSee('Learner, Mine')->assertDontSee('Learner, Other');
    }

    public function test_grades_report_filters_by_final_grade_band_or_custom_range_and_ranks_high_to_low(): void
    {
        $this->gradeFor($this->enrolledLearner('Low', 'Scorer', $this->faith), 'MATH', 78.0);
        $this->gradeFor($this->enrolledLearner('Mid', 'Scorer', $this->faith), 'MATH', 83.5);
        $this->gradeFor($this->enrolledLearner('Top', 'Scorer', $this->hope), 'MATH', 96.0);

        $this->actingAs($this->admin)->get(route('reporting.show', ['grades', 'band' => '80_84']))
            ->assertOk()->assertSee('Scorer, Mid')->assertDontSee('Scorer, Low')->assertDontSee('Scorer, Top');
        $this->actingAs($this->admin)->get(route('reporting.show', ['grades', 'min_grade' => 80, 'max_grade' => 100, 'sort' => 'final_grade', 'direction' => 'desc']))
            ->assertOk()->assertSeeInOrder(['Scorer, Top', 'Scorer, Mid'])->assertDontSee('Scorer, Low');
        $this->actingAs($this->admin)->get(route('reporting.show', ['grades', 'section' => $this->hope->public_id]))
            ->assertOk()->assertSee('Scorer, Top')->assertDontSee('Scorer, Mid');
        $this->actingAs($this->admin)->get(route('reporting.show', ['grades', 'min_grade' => 90, 'max_grade' => 80]))
            ->assertRedirect()->assertSessionHasErrors('max_grade');
        // Grade filters belong to the grades report only.
        $this->actingAs($this->admin)->get(route('reporting.show', ['payments', 'band' => '80_84']))
            ->assertRedirect()->assertSessionHasErrors('filters');
    }

    public function test_enrollment_print_keeps_the_summary_but_can_print_the_matching_records(): void
    {
        $learner = $this->enrolledLearner('Dana', 'Lim', $this->faith);

        $this->actingAs($this->admin)->get(route('reporting.print', 'enrollments'))
            ->assertOk()->assertSee('Enrolment Data')->assertDontSee($learner->application_number);
        $this->actingAs($this->admin)->get(route('reporting.print', ['enrollments', 'layout' => 'detail']))
            ->assertOk()->assertSee($learner->application_number)->assertSee('Lim, Dana');
    }

    private function enrolledLearner(string $first, string $last, Section $section): Enrollment
    {
        $student = StudentProfile::query()->create(['student_number' => 'JCA-26-'.random_int(100, 999).Str::random(3), 'first_name' => $first, 'last_name' => $last]);
        $enrollment = Enrollment::query()->create(['application_number' => 'APP-'.Str::random(10), 'student_id' => $student->id, 'academic_year_id' => $this->year->id, 'grade_level_id' => $this->grade->id, 'student_category' => 'regular', 'status' => 'enrolled', 'active_enrollment_key' => $student->id.':'.$this->year->id, 'submitted_at' => now()->subDay(), 'approved_at' => now(), 'enrolled_at' => now()]);
        SectionAssignment::query()->create(['enrollment_id' => $enrollment->id, 'section_id' => $section->id, 'status' => 'confirmed', 'active_assignment_key' => (string) $enrollment->id, 'confirmed_by' => $this->admin->id, 'confirmed_at' => now(), 'effective_from' => today()]);

        return $enrollment->load('student');
    }

    private function paidLearner(string $first, string $last, Section $section, string $reference, string $amount): Enrollment
    {
        $enrollment = $this->enrolledLearner($first, $last, $section);
        $schedule = FeeSchedule::query()->firstOrCreate(['academic_year_id' => $this->year->id, 'grade_level_id' => $this->grade->id, 'version' => 1], ['name' => 'Report schedule', 'status' => 'published', 'published_by' => $this->admin->id, 'published_at' => now()]);
        $assessment = FeeAssessment::query()->create(['assessment_reference' => 'ASM-'.$reference, 'enrollment_id' => $enrollment->id, 'fee_schedule_id' => $schedule->id, 'revision_number' => 1, 'status' => 'finalized', 'gross_total' => '20000.00', 'discount_total' => '0.00', 'net_total' => '20000.00', 'created_by' => $this->admin->id, 'finalized_by' => $this->admin->id, 'finalized_at' => now(), 'current_finalized_key' => (string) $enrollment->id]);
        Payment::query()->create(['payment_reference' => $reference, 'receipt_reference' => 'REC-'.$reference, 'fee_assessment_id' => $assessment->id, 'received_on' => today(), 'amount' => $amount, 'method' => 'cash', 'payer_name' => 'Fictional Payer', 'status' => 'posted', 'created_by' => $this->admin->id]);

        return $enrollment;
    }

    private function gradeFor(Enrollment $enrollment, string $code, float $final): void
    {
        $subject = Subject::query()->firstOrCreate(['code' => $code], ['name' => 'Mathematics']);
        $sectionId = SectionAssignment::query()->where('enrollment_id', $enrollment->id)->value('section_id');
        $offering = SectionSubject::query()->firstOrCreate(['section_id' => $sectionId, 'subject_id' => $subject->id]);
        $staff = StaffProfile::query()->firstOrCreate(['employee_number' => 'RPT-T'.$sectionId], ['user_id' => $this->user(['teacher'])->id]);
        $assignment = TeachingAssignment::query()->firstOrCreate(['staff_profile_id' => $staff->id, 'section_id' => $sectionId, 'section_subject_id' => $offering->id], ['assignment_type' => 'subject', 'is_active' => true]);
        StudentSubjectGrade::query()->create([
            'enrollment_id' => $enrollment->id, 'subject_id' => $subject->id, 'section_subject_id' => $offering->id, 'teaching_assignment_id' => $assignment->id,
            'subject_code_snapshot' => $code, 'subject_name_snapshot' => 'Mathematics',
            'quarter_1' => $final, 'quarter_2' => $final, 'quarter_3' => $final, 'quarter_4' => $final, 'final_grade' => $final,
            'status' => 'finalized', 'finalized_by' => $this->admin->id, 'finalized_at' => now(),
        ]);
    }

    /** @param list<string> $roles */
    private function user(array $roles): User
    {
        $user = User::query()->create(['name' => 'Fictional '.Str::random(6), 'email' => Str::lower(Str::random(12)).'@example.test', 'password' => Hash::make('Password12345'), 'status' => AccountStatus::Active, 'password_changed_at' => now()]);

        return app(AssignRolesAction::class)->execute($user, $roles, null);
    }
}
