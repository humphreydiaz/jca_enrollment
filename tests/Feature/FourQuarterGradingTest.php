<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Grading\Models\GradeCorrectionRequest;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\StaffProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FourQuarterGradingTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $admin;

    private TeachingAssignment $assignment;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->teacher = $this->user('teacher');
        $this->admin = $this->user('admin');
        $staff = StaffProfile::query()->create(['user_id' => $this->teacher->id, 'active_from' => today()]);
        $year = AcademicYear::query()->create(['code' => 'TEST-26', 'name' => 'Test school year', 'starts_on' => '2026-06-01', 'ends_on' => '2027-04-30', 'status' => 'open']);
        $level = GradeLevel::query()->create(['code' => 'G1', 'name' => 'Grade 1', 'sequence' => 1, 'school_stage' => 'Elementary']);
        $section = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $level->id, 'name' => 'Hope', 'capacity' => 20, 'status' => 'active']);
        $subject = Subject::query()->create(['code' => 'MATH', 'name' => 'Mathematics']);
        $offering = SectionSubject::query()->create(['section_id' => $section->id, 'subject_id' => $subject->id]);
        $this->assignment = TeachingAssignment::query()->create(['staff_profile_id' => $staff->id, 'section_id' => $section->id, 'section_subject_id' => $offering->id, 'assignment_type' => 'subject_teacher', 'is_active' => true]);
        $student = StudentProfile::query()->create(['first_name' => 'Fictional', 'last_name' => 'Learner', 'student_number' => 'JCA-26-991']);
        $guardian = GuardianProfile::query()->create(['first_name' => 'Fictional', 'last_name' => 'Parent']);
        $this->enrollment = Enrollment::query()->create(['application_number' => 'GRADE-'.Str::upper(Str::random(8)), 'student_id' => $student->id, 'academic_year_id' => $year->id, 'grade_level_id' => $level->id, 'applicant_guardian_id' => $guardian->id, 'student_category' => 'regular', 'status' => 'enrolled', 'active_enrollment_key' => $student->id.':'.$year->id, 'enrolled_at' => now()]);
        SectionAssignment::query()->create(['enrollment_id' => $this->enrollment->id, 'section_id' => $section->id, 'status' => 'confirmed', 'active_assignment_key' => (string) $this->enrollment->id, 'confirmed_by' => $this->admin->id, 'confirmed_at' => now(), 'effective_from' => today()]);
    }

    public function test_four_quarter_calculation_submission_finalization_and_family_release(): void
    {
        $guardianUser = $this->user('guardian');
        $guardian = $this->enrollment->guardian;
        $guardian->update(['user_id' => $guardianUser->id]);
        GuardianStudent::query()->create(['guardian_id' => $guardian->id, 'student_id' => $this->enrollment->student_id, 'relationship_type' => 'mother', 'verified_at' => now(), 'may_manage_enrollment' => true]);

        $save = route('grading.teacher.save', [$this->assignment, $this->enrollment]);
        $this->post($save, ['quarter_1' => 90])->assertRedirect(route('identity.login'));
        $this->actingAs($this->teacher)->get(route('grading.teacher.show', $this->assignment))->assertOk()->assertSee('Mathematics');
        $this->actingAs($this->teacher)->post($save, ['quarter_1' => 90, 'final_grade' => 100])->assertRedirect();
        $grade = StudentSubjectGrade::query()->firstOrFail();
        $this->assertNull($grade->final_grade);
        $this->actingAs($guardianUser)->get(route('grading.family.index'))->assertDontSee('Mathematics');
        $this->actingAs($this->teacher)->post(route('grading.teacher.submit', [$this->assignment, $grade]))->assertSessionHasErrors('grade');

        $this->actingAs($this->teacher)->post($save, ['quarter_1' => 90, 'quarter_2' => 91, 'quarter_3' => 92, 'quarter_4' => 93])->assertRedirect();
        $this->assertSame(92, $grade->fresh()->final_grade); // 91.5 rounds upward.
        $this->actingAs($this->teacher)->post(route('grading.teacher.submit', [$this->assignment, $grade]))->assertRedirect();
        $this->actingAs($this->admin)->get(route('grading.admin.index'))->assertOk()->assertSee('Waiting for approval')->assertSee('1 of 1 submitted');
        $this->actingAs($this->admin)->get(route('grading.admin.class', [$this->assignment->section, $this->assignment->sectionSubject->subject]))->assertOk()->assertSee('Approve all 1');
        $this->actingAs($this->teacher)->post($save, ['quarter_1' => 100, 'quarter_2' => 100, 'quarter_3' => 100, 'quarter_4' => 100])->assertSessionHasErrors('grade');
        $this->actingAs($guardianUser)->get(route('grading.family.index'))->assertDontSee('Mathematics');
        $this->actingAs($this->admin)->post(route('grading.admin.finalize', $grade))->assertRedirect();
        $this->actingAs($guardianUser)->get(route('grading.family.index'))->assertOk()->assertSee('Mathematics')->assertSee('92')
            ->assertSee('>Teacher</th>', false)->assertDontSee('>Student</th>', false)
            ->assertSeeInOrder([$this->enrollment->student->full_name, 'Test school year', 'Mathematics', $this->teacher->name, '92']);
        $this->assertDatabaseCount('grading_periods', 4);
        $this->assertDatabaseHas('audit_logs', ['action' => 'grading.finalized']);
    }

    public function test_teacher_requests_admin_reopen_and_every_change_is_preserved(): void
    {
        $grade = $this->finalizedGrade();
        $this->actingAs($this->teacher)->post(route('grading.teacher.correction', [$this->assignment, $grade]), ['reason' => 'Correct the recorded first quarter mark.'])->assertRedirect();
        $correction = GradeCorrectionRequest::query()->firstOrFail();
        $this->actingAs($this->admin)->get(route('grading.admin.index', ['tab' => 'reopen']))->assertOk()->assertSee('Approve reopen');
        $this->actingAs($this->teacher)->post(route('grading.teacher.correction', [$this->assignment, $grade]), ['reason' => 'Duplicate request.'])->assertSessionHasErrors('grade');
        $this->actingAs($this->admin)->post(route('grading.admin.corrections.review', $correction), ['decision' => 'approve', 'review_note' => 'Checked the teacher evidence.'])->assertRedirect();
        $this->assertSame('draft', $grade->fresh()->status);
        $this->actingAs($this->teacher)->post(route('grading.teacher.save', [$this->assignment, $this->enrollment]), ['quarter_1' => 100, 'quarter_2' => 80, 'quarter_3' => 80, 'quarter_4' => 80])->assertRedirect();
        $this->assertSame(85, $grade->fresh()->final_grade);
        $this->assertDatabaseHas('grade_revisions', ['student_subject_grade_id' => $grade->id, 'event_type' => 'quarter_saved', 'quarter_number' => 1, 'old_value' => 80, 'new_value' => 100]);
        $this->actingAs($this->teacher)->post(route('grading.teacher.submit', [$this->assignment, $grade]))->assertRedirect();
        $this->actingAs($this->admin)->post(route('grading.admin.finalize', $grade))->assertRedirect();
        $this->assertSame('finalized', $grade->fresh()->status);
    }

    public function test_other_roles_families_and_assignments_cannot_access_or_mutate_grades(): void
    {
        $grade = $this->finalizedGrade();
        $otherTeacher = $this->user('teacher');
        $otherGuardian = $this->user('guardian');
        $registrar = $this->user('registrar');
        $accounting = $this->user('accounting');

        $this->actingAs($otherTeacher)->get(route('grading.teacher.show', $this->assignment))->assertForbidden();
        $this->actingAs($otherTeacher)->post(route('grading.teacher.save', [$this->assignment, $this->enrollment]), ['quarter_1' => 100])->assertForbidden();
        // The Registrar approves final grades (D-043), but may not enter or change quarter values.
        $this->actingAs($registrar)->get(route('grading.admin.index'))->assertOk();
        $this->actingAs($registrar)->post(route('grading.teacher.save', [$this->assignment, $this->enrollment]), ['quarter_1' => 100])->assertForbidden();
        $this->actingAs($accounting)->get(route('grading.admin.index'))->assertForbidden();
        $this->actingAs($accounting)->post(route('grading.admin.finalize', $grade))->assertForbidden();
        $this->actingAs($otherTeacher)->post(route('grading.admin.approve-class'), ['grade_ids' => [$grade->public_id]])->assertForbidden();
        $this->actingAs($accounting)->get(route('grading.family.index'))->assertForbidden();
        $this->actingAs($otherGuardian)->get(route('grading.family.index'))->assertOk()->assertDontSee('Mathematics');
    }

    public function test_locked_accounts_cannot_read_grading_pages(): void
    {
        $this->teacher->update(['locked_at' => now()]);
        $this->actingAs($this->teacher)->get(route('grading.teacher.index'))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('grading.teacher.show', $this->assignment))->assertForbidden();

        $this->admin->update(['locked_at' => now()]);
        $this->actingAs($this->admin)->get(route('grading.admin.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('reporting.show', 'grades'))->assertForbidden();

        $guardian = $this->user('guardian');
        $guardian->update(['locked_at' => now()]);
        $this->actingAs($guardian)->get(route('grading.family.index'))->assertForbidden();
    }

    public function test_validation_and_database_uniqueness_protect_grade_records(): void
    {
        $save = route('grading.teacher.save', [$this->assignment, $this->enrollment]);
        $this->actingAs($this->teacher)->post($save, ['quarter_1' => 101])->assertSessionHasErrors('quarter_1');
        $this->actingAs($this->teacher)->post($save, ['quarter_1' => 82.5])->assertSessionHasErrors('quarter_1');
        $this->assertDatabaseCount('student_subject_grades', 0);
        $this->actingAs($this->teacher)->post($save, ['quarter_1' => 82])->assertRedirect();
        $grade = StudentSubjectGrade::query()->firstOrFail();
        $this->expectException(QueryException::class);
        StudentSubjectGrade::query()->create($grade->only(['enrollment_id', 'subject_id', 'section_subject_id', 'teaching_assignment_id', 'subject_code_snapshot', 'subject_name_snapshot']));
    }

    public function test_admin_report_never_exports_unfinalized_grades(): void
    {
        $this->actingAs($this->teacher)->post(route('grading.teacher.save', [$this->assignment, $this->enrollment]), ['quarter_1' => 88, 'quarter_2' => 88, 'quarter_3' => 88, 'quarter_4' => 88])->assertRedirect();
        $grade = StudentSubjectGrade::query()->firstOrFail();
        $this->actingAs($this->admin)->get(route('reporting.show', 'grades'))->assertOk()->assertDontSee('Mathematics');
        $this->actingAs($this->teacher)->get(route('reporting.show', 'grades'))->assertForbidden();
        $this->actingAs($this->teacher)->post(route('grading.teacher.submit', [$this->assignment, $grade]))->assertRedirect();
        $this->actingAs($this->admin)->get(route('reporting.show', 'grades'))->assertDontSee('Mathematics');
        $this->actingAs($this->admin)->post(route('grading.admin.finalize', $grade))->assertRedirect();
        $this->actingAs($this->admin)->get(route('reporting.show', 'grades'))->assertOk()->assertSee('Mathematics')->assertSee('88');
    }

    public function test_preschool_subjects_cannot_be_numeric_graded(): void
    {
        $level = GradeLevel::query()->create(['code' => 'NUR', 'name' => 'Nursery', 'sequence' => 2, 'school_stage' => 'Pre-elementary']);
        $section = Section::query()->create(['academic_year_id' => $this->enrollment->academic_year_id, 'grade_level_id' => $level->id, 'name' => 'Nursery Hope', 'capacity' => 20, 'status' => 'active']);
        $offering = SectionSubject::query()->create(['section_id' => $section->id, 'subject_id' => $this->assignment->sectionSubject->subject_id]);
        $assignment = TeachingAssignment::query()->create(['staff_profile_id' => $this->assignment->staff_profile_id, 'section_id' => $section->id, 'section_subject_id' => $offering->id, 'assignment_type' => 'subject_teacher', 'is_active' => true]);
        $this->actingAs($this->teacher)->get(route('grading.teacher.show', $assignment))->assertForbidden();
        $this->actingAs($this->teacher)->post(route('grading.teacher.save', [$assignment, $this->enrollment]), ['quarter_1' => 90])->assertForbidden();
    }

    public function test_admin_rejection_preserves_finalized_result_and_approval_hides_it_until_refinalized(): void
    {
        $grade = $this->finalizedGrade();
        $guardianUser = $this->user('guardian');
        $this->enrollment->guardian->update(['user_id' => $guardianUser->id]);
        GuardianStudent::query()->create(['guardian_id' => $this->enrollment->applicant_guardian_id, 'student_id' => $this->enrollment->student_id, 'relationship_type' => 'mother', 'verified_at' => now(), 'may_manage_enrollment' => true]);
        $this->actingAs($guardianUser)->get(route('grading.family.index'))->assertSee('Mathematics');

        $this->actingAs($this->teacher)->post(route('grading.teacher.correction', [$this->assignment, $grade]), ['reason' => 'Review the first quarter source.'])->assertRedirect();
        $first = GradeCorrectionRequest::query()->firstOrFail();
        $this->actingAs($this->admin)->post(route('grading.admin.corrections.review', $first), ['decision' => 'reject', 'review_note' => 'Original mark is correct.'])->assertRedirect();
        $this->assertSame('finalized', $grade->fresh()->status);
        $this->actingAs($guardianUser)->get(route('grading.family.index'))->assertSee('Mathematics');

        $this->actingAs($this->teacher)->post(route('grading.teacher.correction', [$this->assignment, $grade]), ['reason' => 'New verified source document.'])->assertRedirect();
        $second = GradeCorrectionRequest::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->post(route('grading.admin.corrections.review', $second), ['decision' => 'approve', 'review_note' => 'Verified corrected document.'])->assertRedirect();
        $this->actingAs($guardianUser)->get(route('grading.family.index'))->assertDontSee('Mathematics');
        $this->actingAs($this->admin)->get(route('reporting.show', 'grades'))->assertDontSee('Mathematics');
        $this->actingAs($this->admin)->post(route('grading.admin.corrections.review', $second), ['decision' => 'approve', 'review_note' => 'Duplicate decision attempt.'])->assertSessionHasErrors('grade');
    }

    public function test_mysql_rejects_a_final_value_not_derived_from_four_quarters(): void
    {
        $grade = $this->finalizedGrade();
        $this->expectException(QueryException::class);
        DB::table('student_subject_grades')->where('id', $grade->id)->update(['final_grade' => 99]);
    }

    public function test_grade_access_stays_scoped_to_the_assigned_subject_and_student_identity(): void
    {
        $grade = $this->finalizedGrade();
        $otherTeacher = $this->user('teacher');
        $staff = StaffProfile::query()->create(['user_id' => $otherTeacher->id, 'active_from' => today()]);
        $science = Subject::query()->create(['code' => 'SCI', 'name' => 'Science']);
        $offering = SectionSubject::query()->create(['section_id' => $this->assignment->section_id, 'subject_id' => $science->id]);
        $scienceAssignment = TeachingAssignment::query()->create(['staff_profile_id' => $staff->id, 'section_id' => $this->assignment->section_id, 'section_subject_id' => $offering->id, 'assignment_type' => 'subject_teacher', 'is_active' => true]);
        $this->actingAs($otherTeacher)->post(route('grading.teacher.submit', [$scienceAssignment, $grade]))->assertForbidden();

        $studentUser = $this->user('student');
        $studentUser->update(['name' => 'Fictional Learner']);
        $this->enrollment->student->update(['user_id' => $studentUser->id]);
        $this->actingAs($studentUser)->get(route('grading.family.index'))->assertOk()->assertSee('Mathematics');
        $studentUser->update(['name' => 'Another Learner']);
        $this->actingAs($studentUser)->get(route('grading.family.index'))->assertDontSee('Mathematics');
    }

    public function test_finalized_grade_keeps_its_subject_snapshot_when_catalog_name_changes(): void
    {
        $grade = $this->finalizedGrade();
        $grade->subject->update(['name' => 'Renamed catalog subject']);
        $this->assertSame('Mathematics', $grade->fresh()->subject_name_snapshot);
        $this->actingAs($this->admin)->get(route('reporting.show', 'grades'))
            ->assertSee('Mathematics')->assertDontSee('Renamed catalog subject');
    }

    private function finalizedGrade(): StudentSubjectGrade
    {
        $this->actingAs($this->teacher)->post(route('grading.teacher.save', [$this->assignment, $this->enrollment]), ['quarter_1' => 80, 'quarter_2' => 80, 'quarter_3' => 80, 'quarter_4' => 80])->assertRedirect();
        $grade = StudentSubjectGrade::query()->firstOrFail();
        $this->actingAs($this->teacher)->post(route('grading.teacher.submit', [$this->assignment, $grade]))->assertRedirect();
        $this->actingAs($this->admin)->post(route('grading.admin.finalize', $grade))->assertRedirect();

        return $grade->fresh();
    }

    private function user(string $role): User
    {
        $user = User::query()->create(['name' => 'Fictional '.Str::random(8), 'email' => Str::lower(Str::random(10)).'@example.test', 'password' => 'Password12345', 'status' => AccountStatus::Active, 'password_changed_at' => now()]);

        return app(AssignRolesAction::class)->execute($user, [$role], null);
    }
}
