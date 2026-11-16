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
use App\Modules\Billing\Models\SiblingDiscountConfirmation;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\StaffProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Reporting\Reports\ReportCatalog;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Pre-final panel, Registrar: retention report (D-066), sibling discount (D-067), directory print. */
class RegistrarRetentionAndSiblingDiscountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $registrar;

    private AcademicYear $lastYear;

    private AcademicYear $thisYear;

    private GradeLevel $grade3;

    private GradeLevel $grade4;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = $this->user(['admin']);
        $this->registrar = $this->user(['registrar']);
        $this->lastYear = AcademicYear::query()->create(['code' => 'RT-25', 'name' => 'School Year 2025-2026', 'starts_on' => '2025-06-01', 'ends_on' => '2026-03-31', 'status' => 'closed']);
        $this->thisYear = AcademicYear::query()->create(['code' => 'RT-26', 'name' => 'School Year 2026-2027', 'starts_on' => '2026-06-01', 'ends_on' => '2027-03-31', 'status' => 'open']);
        $this->grade3 = GradeLevel::query()->create(['code' => 'RT-G3', 'name' => 'Grade 3', 'sequence' => 9003, 'is_active' => true]);
        $this->grade4 = GradeLevel::query()->create(['code' => 'RT-G4', 'name' => 'Grade 4', 'sequence' => 9004, 'is_active' => true]);
    }

    public function test_retention_report_lists_each_outcome_with_details_and_a_summary(): void
    {
        $continuing = $this->learner('Cont', 'Inuing');
        $this->enroll($continuing, $this->lastYear, $this->grade3);
        $this->enroll($continuing, $this->thisYear, $this->grade4);

        $gone = $this->learner('Gone', 'Away');
        $this->enroll($gone, $this->lastYear, $this->grade3);

        $repeater = $this->learner('Re', 'Peater');
        $this->enroll($repeater, $this->lastYear, $this->grade3);
        $this->enroll($repeater, $this->thisYear, $this->grade3);

        $transferee = $this->learner('Trans', 'Feree', ['status' => 'transferred_out', 'exit_category' => 'transferred_out', 'exit_receiving_school' => 'Bolinao Central School', 'transferred_out_at' => '2026-08-01', 'archived_at' => now()]);
        $this->enroll($transferee, $this->thisYear, $this->grade4);

        $expelled = $this->learner('Ex', 'Pelled', ['status' => 'dropped', 'exit_category' => 'excluded', 'dropped_at' => '2026-09-01', 'archived_at' => now()]);
        $this->enroll($expelled, $this->thisYear, $this->grade4);

        $mover = $this->learner('Sec', 'Mover');
        $moved = $this->enroll($mover, $this->thisYear, $this->grade4, 'Faith');
        SectionAssignment::query()->where('enrollment_id', $moved->id)->update(['ended_at' => now(), 'status' => 'transferred', 'active_assignment_key' => null]);
        $hope = Section::query()->create(['academic_year_id' => $this->thisYear->id, 'grade_level_id' => $this->grade4->id, 'name' => 'Hope', 'capacity' => 30, 'status' => 'active']);
        SectionAssignment::query()->create(['enrollment_id' => $moved->id, 'section_id' => $hope->id, 'status' => 'confirmed', 'active_assignment_key' => (string) $moved->id, 'confirmed_by' => $this->admin->id, 'confirmed_at' => now(), 'effective_from' => today()]);

        $struggling = $this->learner('Low', 'Grades');
        $this->finalGrade($this->enroll($struggling, $this->thisYear, $this->grade4, 'Faith'), 72);

        $filters = ['school_year' => $this->thisYear->public_id];
        $page = $this->actingAs($this->registrar)->get(route('reporting.show', ['retention'] + $filters));
        $page->assertOk()
            ->assertSee('Retention — School Year 2026-2027')
            ->assertSee('came back (66.7% retention)')
            ->assertSee('Away, Gone')->assertSee('No enrollment for School Year 2026-2027')
            ->assertSee('Peater, Re')->assertSee('Same grade level as School Year 2025-2026')
            ->assertSee('Feree, Trans')->assertSee('To Bolinao Central School')
            ->assertSee('Pelled, Ex')->assertSee('Excluded / expelled')
            ->assertSee('Mover, Sec')->assertSee('Moved from Faith to Hope')
            ->assertSee('Grades, Low')->assertSee('1 subject(s): Mathematics 72')
            ->assertDontSee('Inuing, Cont');

        $summary = app(ReportCatalog::class)->retentionSummary($filters);
        $this->assertSame(1, $summary['counts']['not_returned']);
        $this->assertSame(1, $summary['counts']['repeating']);
        $this->assertSame(1, $summary['counts']['transferred_out']);
        $this->assertSame(1, $summary['counts']['excluded']);
        $this->assertSame(1, $summary['counts']['changed_section']);
        $this->assertSame(1, $summary['counts']['below_75']);
        $this->assertSame(2, $summary['continuing']);
        $this->assertSame(6, $summary['enrolled']); // exits keep their enrollment record (D-061)

        // Filter to one outcome and print it with the summary.
        $this->actingAs($this->registrar)->get(route('reporting.show', ['retention', 'status' => 'transferred_out'] + $filters))
            ->assertOk()->assertSee('Feree, Trans')->assertDontSee('Away, Gone');
        $this->actingAs($this->registrar)->get(route('reporting.print', ['retention'] + $filters))
            ->assertOk()->assertSee('Retention — School Year 2026-2027')->assertSee('Away, Gone');

        $this->actingAs($this->user(['accounting']))->get(route('reporting.show', 'retention'))->assertForbidden();
        $this->actingAs($this->user(['teacher']))->get(route('reporting.show', 'retention'))->assertForbidden();
    }

    public function test_registrar_confirms_and_withdraws_a_sibling_discount_and_accounting_sees_it(): void
    {
        $parent = GuardianProfile::query()->create(['first_name' => 'Lorna', 'last_name' => 'Reyes']);
        $older = $this->learner('Mika', 'Reyes');
        $younger = $this->learner('Paolo', 'Reyes');
        $olderEnrollment = $this->enroll($older, $this->thisYear, $this->grade4);
        $this->enroll($younger, $this->thisYear, $this->grade3);
        foreach ([$older, $younger] as $child) {
            GuardianStudent::query()->create(['guardian_id' => $parent->id, 'student_id' => $child->id, 'relationship_type' => 'mother', 'verified_at' => now(), 'effective_from' => today()]);
        }
        $only = $this->learner('Only', 'Child');
        $onlyEnrollment = $this->enroll($only, $this->thisYear, $this->grade3);

        $this->actingAs($this->registrar)->get(route('people.admin.index', ['search' => 'Mika']))->assertOk()
            ->assertSee('Siblings at JCA this school year')->assertSee('Paolo Reyes (Grade 3)')->assertSee('Confirm sibling discount');

        $this->actingAs($this->user(['accounting']))->post(route('billing.sibling-discount.store', $olderEnrollment))->assertForbidden();
        $this->actingAs($this->registrar)->post(route('billing.sibling-discount.store', $onlyEnrollment))->assertSessionHasErrors('sibling_discount');

        $this->actingAs($this->registrar)->post(route('billing.sibling-discount.store', $olderEnrollment))->assertSessionHasNoErrors();
        $confirmation = SiblingDiscountConfirmation::query()->sole();
        $this->assertSame('Paolo Reyes (Grade 3)', $confirmation->sibling_summary);
        $this->assertDatabaseHas('audit_logs', ['action' => 'billing.sibling_discount.confirmed', 'actor_user_id' => $this->registrar->id]);
        $this->actingAs($this->registrar)->post(route('billing.sibling-discount.store', $olderEnrollment))->assertSessionHasErrors('sibling_discount');

        // Accounting sees the confirmation on the assessment and applies it as an adjustment.
        $assessment = $this->assessment($olderEnrollment);
        $this->actingAs($this->user(['accounting']))->get(route('billing.portal.show', $assessment))->assertOk()
            ->assertSee('Sibling discount confirmed')->assertSee('Paolo Reyes (Grade 3)');

        // Withdrawing needs a reason and keeps the record.
        $this->actingAs($this->registrar)->delete(route('billing.sibling-discount.destroy', $confirmation))->assertSessionHasErrors('reason');
        $this->actingAs($this->registrar)->delete(route('billing.sibling-discount.destroy', $confirmation), ['reason' => 'Sibling transferred out'])->assertSessionHasNoErrors();
        $this->assertNotNull($confirmation->fresh()->withdrawn_at);
        $this->assertNull($confirmation->fresh()->active_key);
        $this->assertSame(1, SiblingDiscountConfirmation::query()->count());
        $this->actingAs($this->user(['accounting']))->get(route('billing.portal.show', $assessment))->assertOk()->assertDontSee('Sibling discount confirmed');
        // It can be confirmed again later.
        $this->actingAs($this->registrar)->post(route('billing.sibling-discount.store', $olderEnrollment))->assertSessionHasNoErrors();
        $this->assertSame(2, SiblingDiscountConfirmation::query()->count());
    }

    public function test_student_directory_offers_print_and_the_archive_links_to_the_retention_report(): void
    {
        $this->actingAs($this->registrar)->get(route('people.registrar.students.index', ['grade' => $this->grade3->public_id]))->assertOk()
            ->assertSee('Print directory')
            ->assertSee(route('reporting.print', ['students', 'grade' => $this->grade3->public_id]), false);
        $this->actingAs($this->registrar)->get(route('people.registrar.students.index', ['archive' => 1]))->assertOk()
            ->assertSee('Retention and exits report');
    }

    /** @param array<string, mixed> $attributes */
    private function learner(string $first, string $last, array $attributes = []): StudentProfile
    {
        $student = StudentProfile::query()->create(['student_number' => 'JCA-26-'.random_int(100, 999).Str::random(2), 'first_name' => $first, 'last_name' => $last]);
        // Exit columns are set by RecordStudentExitAction in the app; fixtures set them directly.
        $student->forceFill($attributes)->save();

        return $student;
    }

    private function enroll(StudentProfile $student, AcademicYear $year, GradeLevel $grade, ?string $sectionName = null): Enrollment
    {
        $enrollment = Enrollment::query()->create(['application_number' => 'APP-'.Str::random(10), 'student_id' => $student->id, 'academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'student_category' => 'regular', 'status' => 'enrolled', 'active_enrollment_key' => $student->id.':'.$year->id, 'submitted_at' => now(), 'approved_at' => now(), 'enrolled_at' => now()]);
        if ($sectionName !== null) {
            $section = Section::query()->firstOrCreate(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => $sectionName], ['capacity' => 30, 'status' => 'active']);
            SectionAssignment::query()->create(['enrollment_id' => $enrollment->id, 'section_id' => $section->id, 'status' => 'confirmed', 'active_assignment_key' => (string) $enrollment->id, 'confirmed_by' => $this->admin->id, 'confirmed_at' => now(), 'effective_from' => today()->subMonth()]);
        }

        return $enrollment;
    }

    private function finalGrade(Enrollment $enrollment, float $final): void
    {
        $subject = Subject::query()->firstOrCreate(['code' => 'RT-MATH'], ['name' => 'Mathematics']);
        $sectionId = SectionAssignment::query()->where('enrollment_id', $enrollment->id)->value('section_id');
        $offering = SectionSubject::query()->firstOrCreate(['section_id' => $sectionId, 'subject_id' => $subject->id]);
        $staff = StaffProfile::query()->create(['user_id' => $this->user(['teacher'])->id, 'employee_number' => 'RT-T1']);
        $assignment = TeachingAssignment::query()->create(['staff_profile_id' => $staff->id, 'section_id' => $sectionId, 'section_subject_id' => $offering->id, 'assignment_type' => 'subject', 'is_active' => true]);
        StudentSubjectGrade::query()->create(['enrollment_id' => $enrollment->id, 'subject_id' => $subject->id, 'section_subject_id' => $offering->id, 'teaching_assignment_id' => $assignment->id, 'subject_code_snapshot' => 'RT-MATH', 'subject_name_snapshot' => 'Mathematics', 'quarter_1' => $final, 'quarter_2' => $final, 'quarter_3' => $final, 'quarter_4' => $final, 'final_grade' => $final, 'status' => 'finalized', 'finalized_by' => $this->admin->id, 'finalized_at' => now()]);
    }

    private function assessment(Enrollment $enrollment): FeeAssessment
    {
        $schedule = FeeSchedule::query()->create(['academic_year_id' => $this->thisYear->id, 'grade_level_id' => $enrollment->grade_level_id, 'name' => 'Schedule', 'version' => 1, 'status' => 'published', 'published_by' => $this->admin->id, 'published_at' => now()]);

        return FeeAssessment::query()->create(['assessment_reference' => 'ASM-'.Str::random(8), 'enrollment_id' => $enrollment->id, 'fee_schedule_id' => $schedule->id, 'revision_number' => 1, 'status' => 'finalized', 'gross_total' => '10000.00', 'discount_total' => '0.00', 'net_total' => '10000.00', 'created_by' => $this->admin->id, 'finalized_by' => $this->admin->id, 'finalized_at' => now(), 'current_finalized_key' => (string) $enrollment->id]);
    }

    /** @param list<string> $roles */
    private function user(array $roles): User
    {
        $user = User::query()->create(['name' => 'Fictional '.Str::random(6), 'email' => Str::lower(Str::random(12)).'@example.test', 'password' => Hash::make('Password12345'), 'status' => AccountStatus::Active, 'password_changed_at' => now()]);

        return app(AssignRolesAction::class)->execute($user, $roles, null);
    }
}
