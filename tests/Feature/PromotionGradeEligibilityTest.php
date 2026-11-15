<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Enrollment\Actions\TransitionEnrollmentAction;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StaffProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\SectionAssignment\Actions\CreatePromotionDraftAction;
use App\Modules\SectionAssignment\Actions\RecommendPromotionAction;
use App\Modules\SectionAssignment\Models\PromotionRecommendation;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PromotionGradeEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $registrar;

    private User $teacherUser;

    private AcademicYear $year;

    private AcademicYear $futureYear;

    private GradeLevel $grade6;

    private Section $section;

    /** @var array<string, array{offering: SectionSubject, assignment: TeachingAssignment}> */
    private array $classes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->registrar = $this->user('registrar');
        $this->teacherUser = $this->user('teacher');
        $staff = StaffProfile::query()->create(['user_id' => $this->teacherUser->id, 'active_from' => today()->subDay()]);
        $this->year = AcademicYear::query()->create(['code' => '2025-2026', 'name' => 'School Year 2025-2026', 'starts_on' => '2025-06-09', 'ends_on' => '2026-04-08', 'status' => 'open']);
        $this->futureYear = AcademicYear::query()->create(['code' => '2026-2027', 'name' => 'School Year 2026-2027', 'starts_on' => '2026-06-08', 'ends_on' => '2027-04-08', 'status' => 'planned']);
        $grade7 = GradeLevel::query()->create(['code' => 'G7', 'name' => 'Grade 7', 'sequence' => 10, 'school_stage' => 'Junior High School', 'is_active' => true]);
        $this->grade6 = GradeLevel::query()->create(['code' => 'G6', 'name' => 'Grade 6', 'sequence' => 9, 'school_stage' => 'Elementary', 'next_grade_level_id' => $grade7->id, 'is_active' => true]);
        $this->section = Section::query()->create(['academic_year_id' => $this->year->id, 'grade_level_id' => $this->grade6->id, 'name' => 'Matthew', 'capacity' => 15, 'status' => 'active']);
        foreach (['MATH' => 'Mathematics', 'ENG' => 'English'] as $code => $name) {
            $subject = Subject::query()->create(['code' => $code, 'name' => $name, 'is_active' => true]);
            $offering = SectionSubject::query()->create(['section_id' => $this->section->id, 'subject_id' => $subject->id]);
            $this->classes[$code] = [
                'offering' => $offering,
                'assignment' => TeachingAssignment::query()->create(['staff_profile_id' => $staff->id, 'section_id' => $this->section->id, 'section_subject_id' => $offering->id, 'assignment_type' => 'subject_teacher', 'is_active' => true]),
            ];
        }
    }

    public function test_a_student_without_approved_final_grades_is_not_eligible(): void
    {
        $enrollment = $this->enrolledStudent('Jamilla');

        $this->actingAs($this->registrar)->post(route('promotion.recommend', $enrollment), ['target_academic_year_public_id' => $this->futureYear->public_id])->assertRedirect();
        $recommendation = PromotionRecommendation::query()->sole();
        $this->assertSame('grades_pending', $recommendation->eligibility_status);
        $this->assertSame('Waiting for approved final grades in 2 of 2 subjects: English (no grade yet); Mathematics (no grade yet).', $recommendation->reason);
        $this->assertSame($this->futureYear->id, $recommendation->target_academic_year_id);

        try {
            app(CreatePromotionDraftAction::class)->execute($recommendation, $this->registrar);
            $this->fail('A student waiting for final grades must not get a next-year draft.');
        } catch (ValidationException) {
            $this->assertSame(1, Enrollment::query()->count());
        }

        $this->actingAs($this->registrar)->get(route('section-assignment.index', ['promotion_status' => 'grades_pending']))
            ->assertOk()->assertSee('Jamilla')->assertSee('Waiting for grades')->assertSee('Final grades approved: 0 / 2');
    }

    public function test_reviewing_returns_to_the_promotion_tab_with_the_outcome_and_row_highlight(): void
    {
        $enrollment = $this->enrolledStudent('Jamilla');
        $filtered = route('section-assignment.index', ['grade' => $this->grade6->public_id, 'promotion_page' => 1]);

        $this->actingAs($this->registrar)->from($filtered)
            ->post(route('promotion.recommend', $enrollment), ['target_academic_year_public_id' => $this->futureYear->public_id])
            ->assertRedirect($filtered.'#promotion-review')
            ->assertSessionHas('status', 'Jamilla Learner is waiting for approved final grades (0 of 2 subjects approved).')
            ->assertSessionHas('promotion_reviewed', $enrollment->public_id);

        $this->actingAs($this->registrar)->withSession(['promotion_reviewed' => $enrollment->public_id])
            ->get(route('section-assignment.index'))
            ->assertOk()->assertSee('data-just-reviewed', false)->assertSee('Just updated');
        $this->actingAs($this->registrar)->get(route('section-assignment.index'))
            ->assertOk()->assertDontSee('data-just-reviewed', false);

        // A referrer outside the workspace never becomes the redirect target.
        $this->actingAs($this->registrar)->from('https://example.test/elsewhere')
            ->post(route('promotion.mass-recommend'), ['enrollment_public_ids' => [$enrollment->public_id]])
            ->assertRedirect(route('section-assignment.index').'#promotion-review');

        $this->grade($enrollment, 'MATH', [88, 90, 91, 93], 'finalized');
        $this->grade($enrollment, 'ENG', [80, 82, 85, 87], 'finalized');
        $this->actingAs($this->registrar)->from(route('section-assignment.index'))
            ->post(route('promotion.recommend', $enrollment), ['target_academic_year_public_id' => $this->futureYear->public_id])
            ->assertRedirect(route('section-assignment.index').'#promotion-review')
            ->assertSessionHas('status', 'Jamilla Learner is eligible for promotion to Grade 7 · '.$this->futureYear->name.'.');
    }

    public function test_submitted_grades_count_only_after_the_registrar_approves_them(): void
    {
        $enrollment = $this->enrolledStudent('Loisa');
        $math = $this->grade($enrollment, 'MATH', [88, 90, 91, 93], 'submitted');
        $english = $this->grade($enrollment, 'ENG', [80, 82, 85, 87], 'submitted');

        $this->assertSame('grades_pending', app(RecommendPromotionAction::class)->execute($enrollment, $this->registrar)->eligibility_status);
        $this->assertStringContainsString('Mathematics (waiting for Registrar approval)', PromotionRecommendation::query()->sole()->reason);

        $this->actingAs($this->registrar)->get(route('grading.admin.index', ['year' => $this->year->public_id]))
            ->assertOk()->assertSee('Grade 6 · Matthew · Mathematics')->assertSeeText('grades in 2 classes');
        $this->actingAs($this->registrar)->get(route('grading.admin.class', [$this->section, $this->classes['MATH']['offering']->subject]))
            ->assertOk()->assertSee('Approve all 1');
        $this->actingAs($this->teacherUser)->post(route('grading.admin.approve-class'), ['grade_ids' => [$math->public_id]])->assertForbidden();
        $this->actingAs($this->registrar)->post(route('grading.admin.approve-class'), ['grade_ids' => [$math->public_id, $english->public_id]])
            ->assertSessionHas('status', '2 final grades approved.');
        $this->assertSame('finalized', $math->fresh()->status);
        $this->assertSame($this->registrar->id, $math->fresh()->finalized_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'grading.finalized', 'actor_user_id' => $this->registrar->id]);

        $recommendation = app(RecommendPromotionAction::class)->execute($enrollment, $this->registrar);
        $this->assertSame('eligible', $recommendation->eligibility_status);
        $this->assertStringStartsWith('All 2 subjects have approved final grades.', $recommendation->reason);
        $this->actingAs($this->registrar)->get(route('section-assignment.index'))
            ->assertOk()->assertSee('Final grades approved: 2 / 2')->assertSee($this->inJs(route('promotion.create-draft', $recommendation)), false);

        $draft = app(CreatePromotionDraftAction::class)->execute($recommendation, $this->registrar);
        $this->assertSame($this->futureYear->id, $draft->academic_year_id);
        $this->assertSame('G7', $draft->gradeLevel->code);

        // A continuing student's promotion draft needs no new walk-in check to be submitted.
        try {
            app(TransitionEnrollmentAction::class)->execute($draft, 'submitted', $this->registrar);
            $this->assertSame('submitted', $draft->fresh()->status);
        } catch (ValidationException $exception) {
            $this->assertArrayNotHasKey('status', $exception->errors(), 'Promotion drafts must not require walk-in verification.');
        }
        $this->actingAs($this->registrar)->get(route('enrollment.registrar.index'))->assertOk()
            ->assertSee('Continuing student, promoted from Grade 6');

        // A draft with no identity check at all is still blocked.
        $unverified = $this->enrolledStudent('Nobody', withSection: false);
        $unverified->update(['status' => 'draft', 'contact_verified_at' => null]);
        try {
            app(TransitionEnrollmentAction::class)->execute($unverified, 'submitted', $this->registrar);
            $this->fail('An unverified draft must not be submitted.');
        } catch (ValidationException $exception) {
            $this->assertSame(['Registrar walk-in verification is required before submission.'], $exception->errors()['status']);
        }
    }

    public function test_incomplete_or_unsubmitted_grades_keep_the_student_waiting(): void
    {
        $enrollment = $this->enrolledStudent('Zach');
        $this->grade($enrollment, 'MATH', [88, 90, null, null], 'draft');
        $this->grade($enrollment, 'ENG', [80, 82, 85, 87], 'draft');

        $reason = app(RecommendPromotionAction::class)->execute($enrollment, $this->registrar)->reason;
        $this->assertStringContainsString('Mathematics (incomplete: 2 of 4 quarters)', $reason);
        $this->assertStringContainsString('English (not yet submitted by the teacher)', $reason);
    }

    public function test_a_grade_reopened_after_review_blocks_the_draft_until_reviewed_again(): void
    {
        $enrollment = $this->enrolledStudent('Yana');
        $math = $this->grade($enrollment, 'MATH', [88, 90, 91, 93], 'finalized');
        $this->grade($enrollment, 'ENG', [80, 82, 85, 87], 'finalized');
        $recommendation = app(RecommendPromotionAction::class)->execute($enrollment, $this->registrar);
        $this->assertSame('eligible', $recommendation->eligibility_status);

        // The teacher asks to reopen Mathematics and the Registrar approves the reopen.
        $this->actingAs($this->teacherUser)->post(route('grading.teacher.correction', [$this->classes['MATH']['assignment'], $math]), ['reason' => 'Quarter 4 was encoded wrongly.'])->assertRedirect();
        $this->actingAs($this->registrar)->get(route('section-assignment.index'))
            ->assertOk()->assertSee('\\u0022staleEligible\\u0022:true', false)->assertDontSee($this->inJs(route('promotion.create-draft', $recommendation)), false);
        $correction = $math->corrections()->sole();
        $this->actingAs($this->registrar)->post(route('grading.admin.corrections.review', $correction), ['decision' => 'approve', 'review_note' => 'Teacher evidence checked.'])->assertRedirect();
        $this->assertSame('draft', $math->fresh()->status);

        $this->actingAs($this->registrar)->post(route('promotion.create-draft', $recommendation))
            ->assertSessionHasErrors(['promotion' => 'Final grades changed since the last review. Waiting for approved final grades in 1 of 2 subjects: Mathematics (not yet submitted by the teacher). Review eligibility again.']);
        $this->assertSame(1, Enrollment::query()->count());
    }

    public function test_archived_subjects_are_not_required(): void
    {
        $enrollment = $this->enrolledStudent('Isaac');
        $this->grade($enrollment, 'MATH', [88, 90, 91, 93], 'finalized');
        $this->classes['ENG']['offering']->update(['status' => 'archived']);

        $this->assertSame('eligible', app(RecommendPromotionAction::class)->execute($enrollment, $this->registrar)->eligibility_status);
    }

    public function test_preschool_has_no_numeric_grade_requirement_and_grade_school_needs_a_section(): void
    {
        $kinder = GradeLevel::query()->create(['code' => 'SK', 'name' => 'Senior Kinder', 'sequence' => 3, 'school_stage' => 'Pre-elementary', 'next_grade_level_id' => GradeLevel::query()->where('code', 'G7')->value('id'), 'is_active' => true]);
        $preschooler = $this->enrolledStudent('Hope', $kinder, withSection: false);
        $recommendation = app(RecommendPromotionAction::class)->execute($preschooler, $this->registrar);
        $this->assertSame('eligible', $recommendation->eligibility_status);
        $this->assertStringStartsWith('Preschool level: no numeric final grades are required.', $recommendation->reason);

        $unplaced = $this->enrolledStudent('Unplaced', withSection: false);
        $result = app(RecommendPromotionAction::class)->execute($unplaced, $this->registrar);
        $this->assertSame('grades_pending', $result->eligibility_status);
        $this->assertSame('The student has no confirmed section, so the subjects to grade are unknown.', $result->reason);
    }

    public function test_mass_review_reports_students_waiting_for_grades(): void
    {
        $ready = $this->enrolledStudent('Ready');
        $this->grade($ready, 'MATH', [88, 90, 91, 93], 'finalized');
        $this->grade($ready, 'ENG', [80, 82, 85, 87], 'finalized');
        $waiting = $this->enrolledStudent('Waiting');

        $this->actingAs($this->registrar)->post(route('promotion.mass-recommend'), ['enrollment_public_ids' => [$ready->public_id, $waiting->public_id]])
            ->assertSessionHas('status', 'Reviewed 2 students: 1 eligible, 1 waiting for approved final grades, 0 ineligible, 0 skipped.');
        $this->actingAs($this->registrar)->post(route('promotion.mass-create-drafts'), ['enrollment_public_ids' => [$ready->public_id, $waiting->public_id]])
            ->assertSessionHas('status', 'Created 1 promotion drafts. 1 skipped (already created, not eligible, or final grades not yet approved).');
    }

    /** How a URL appears inside the page's @js() data. */
    private function inJs(string $url): string
    {
        return str_replace('/', '\\\\\\/', $url);
    }

    /** @param list<int|null> $quarters */
    private function grade(Enrollment $enrollment, string $code, array $quarters, string $status): StudentSubjectGrade
    {
        $class = $this->classes[$code];
        $complete = ! in_array(null, $quarters, true);

        return StudentSubjectGrade::query()->create([
            'enrollment_id' => $enrollment->id,
            'subject_id' => $class['offering']->subject_id,
            'section_subject_id' => $class['offering']->id,
            'teaching_assignment_id' => $class['assignment']->id,
            'subject_code_snapshot' => $code,
            'subject_name_snapshot' => $class['offering']->subject->name,
            'quarter_1' => $quarters[0], 'quarter_2' => $quarters[1], 'quarter_3' => $quarters[2], 'quarter_4' => $quarters[3],
            'final_grade' => $complete ? intdiv(array_sum($quarters) + 2, 4) : null,
            'status' => $status,
            'submitted_by' => $status === 'draft' ? null : $this->teacherUser->id,
            'submitted_at' => $status === 'draft' ? null : now(),
            'finalized_by' => $status === 'finalized' ? $this->registrar->id : null,
            'finalized_at' => $status === 'finalized' ? now() : null,
        ]);
    }

    private function enrolledStudent(string $firstName, ?GradeLevel $grade = null, bool $withSection = true): Enrollment
    {
        $grade ??= $this->grade6;
        $student = StudentProfile::query()->create(['first_name' => $firstName, 'last_name' => 'Learner']);
        $guardian = GuardianProfile::query()->create(['first_name' => 'Fictional', 'last_name' => 'Guardian']);
        $enrollment = Enrollment::query()->create([
            'application_number' => 'PG-'.Str::upper(Str::random(10)), 'student_id' => $student->id,
            'academic_year_id' => $this->year->id, 'grade_level_id' => $grade->id, 'applicant_guardian_id' => $guardian->id,
            'student_category' => 'regular', 'status' => 'enrolled', 'active_enrollment_key' => $student->id.':'.$this->year->id,
            'contact_verified_at' => now(), 'approved_at' => now(), 'enrolled_at' => now(),
        ]);
        if ($withSection) {
            SectionAssignment::query()->create(['enrollment_id' => $enrollment->id, 'section_id' => $this->section->id, 'status' => 'confirmed', 'active_assignment_key' => (string) $enrollment->id, 'confirmed_by' => $this->registrar->id, 'confirmed_at' => now(), 'effective_from' => today()]);
        }

        return $enrollment;
    }

    private function user(string $role): User
    {
        $user = User::query()->create([
            'name' => ucfirst($role).' '.Str::random(6),
            'email' => Str::lower(Str::random(12)).'@example.test',
            'password' => Hash::make('Password12345'),
            'status' => AccountStatus::Active,
            'password_changed_at' => now(),
        ]);

        return app(AssignRolesAction::class)->execute($user, [$role], null);
    }
}
