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
use App\Modules\Grading\Http\Controllers\AdminGradeController;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Grading\Support\GradeFingerprint;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StaffProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class GradeApprovalBoardTest extends TestCase
{
    use RefreshDatabase;

    private User $registrar;

    private User $mathTeacher;

    private User $englishTeacher;

    private AcademicYear $year;

    private GradeLevel $grade6;

    private Section $matthew;

    private Section $mark;

    /** @var array<string, array{offering: SectionSubject, assignment: TeachingAssignment}> */
    private array $classes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-06 09:00:00');
        $this->seed(RoleSeeder::class);
        $this->registrar = $this->user('registrar', 'Rita Registrar');
        $this->mathTeacher = $this->user('teacher', 'Ana Villanueva');
        $this->englishTeacher = $this->user('teacher', 'Ben Ramos');
        $this->year = AcademicYear::query()->create(['code' => '2026-2027', 'name' => 'School Year 2026-2027', 'starts_on' => '2026-06-08', 'ends_on' => '2027-04-08', 'status' => 'open']);
        $this->grade6 = GradeLevel::query()->create(['code' => 'G6', 'name' => 'Grade 6', 'sequence' => 9, 'is_active' => true]);
        $this->matthew = Section::query()->create(['academic_year_id' => $this->year->id, 'grade_level_id' => $this->grade6->id, 'name' => 'Matthew', 'capacity' => 40, 'status' => 'active', 'sort_order' => 1]);
        $this->mark = Section::query()->create(['academic_year_id' => $this->year->id, 'grade_level_id' => $this->grade6->id, 'name' => 'Mark', 'capacity' => 40, 'status' => 'active', 'sort_order' => 2]);
        $math = Subject::query()->create(['code' => 'MATH', 'name' => 'Mathematics', 'is_active' => true]);
        $english = Subject::query()->create(['code' => 'ENG', 'name' => 'English', 'is_active' => true]);
        $this->classes['matthew-MATH'] = $this->offer($this->matthew, $math, $this->mathTeacher);
        $this->classes['matthew-ENG'] = $this->offer($this->matthew, $english, $this->englishTeacher);
        $this->classes['mark-MATH'] = $this->offer($this->mark, $math, $this->mathTeacher);
    }

    public function test_board_tracks_every_class_with_tabs_filters_and_review_flags(): void
    {
        $ana = $this->enroll('Ana', 'Alpha', $this->matthew);
        $bea = $this->enroll('Bea', 'Beta', $this->matthew);
        $this->enroll('Cid', 'Gamma', $this->matthew);
        $dan = $this->enroll('Dan', 'Delta', $this->mark);
        $this->grade($ana, 'matthew-MATH', [85, 86, 87, 88], 'submitted');
        $this->grade($bea, 'matthew-MATH', [80, 55, 82, 84], 'submitted'); // flagged: quarter below 60
        $this->grade($dan, 'mark-MATH', [90, 90, 90, 90], 'finalized');

        $this->actingAs($this->registrar)->get(route('grading.admin.index'))->assertOk()
            ->assertSee('Submission tracker')->assertSeeText('grades in 1 class')
            ->assertSee('Grade 6 · Matthew · Mathematics')
            ->assertSeeText('75–87')            // range of submitted finals (87 and 75)
            ->assertSeeText('⚑ 1')               // one grade to check
            ->assertSeeText('2 of 3 submitted')
            ->assertSee(route('grading.admin.class', [$this->matthew, $this->classes['matthew-MATH']['offering']->subject]), false)
            ->assertSeeText('Classes fully submitted');

        $this->actingAs($this->registrar)->get(route('grading.admin.index', ['tab' => 'missing']))->assertOk()
            ->assertSeeText('Ben Ramos')->assertSeeText('Grade 6 · Matthew · English')->assertSeeText('3 grades not submitted')
            ->assertSeeText('Ana Villanueva')->assertSeeText('1 grade not submitted');

        $this->actingAs($this->registrar)->get(route('grading.admin.index', ['teacher' => $this->englishTeacher->public_id]))->assertOk()
            ->assertSeeText('Nothing is waiting for approval');
        $this->actingAs($this->registrar)->get(route('grading.admin.index', ['flagged' => 1]))->assertOk()
            ->assertSeeText('Grade 6 · Matthew · Mathematics');
        $this->actingAs($this->registrar)->get(route('grading.admin.index', ['search' => 'Delta']))->assertOk()
            ->assertSeeText('Nothing is waiting for approval');
        $this->actingAs($this->registrar)->get(route('grading.admin.index', ['tab' => 'approved']))->assertOk()
            ->assertSeeText('Dan Delta');
        $this->actingAs($this->registrar)->get(route('grading.admin.index', ['tab' => 'everything']))
            ->assertRedirect()->assertSessionHasErrors('tab');
    }

    public function test_board_has_no_hidden_cap_on_waiting_grades(): void
    {
        $subjects = collect(range(1, 15))->map(fn (int $i) => $this->offer($this->matthew, Subject::query()->create(['code' => 'S'.$i, 'name' => 'Subject '.$i, 'is_active' => true]), $this->mathTeacher));
        $students = collect(range(1, 35))->map(fn (int $i) => $this->enroll('Student'.$i, 'Learner'.$i, $this->matthew));
        foreach ($subjects as $class) {
            foreach ($students as $enrollment) {
                $this->rawGrade($enrollment, $class, [85, 86, 87, 88], 'submitted');
            }
        }

        $this->actingAs($this->registrar)->get(route('grading.admin.index'))->assertOk()
            ->assertSeeText('525')->assertSeeText('grades in 15 classes');
        $this->assertSame(525, StudentSubjectGrade::query()->where('status', 'submitted')->count());
    }

    public function test_classes_are_approved_only_after_review_and_only_as_reviewed(): void
    {
        $ana = $this->enroll('Ana', 'Alpha', $this->matthew);
        $bea = $this->enroll('Bea', 'Beta', $this->matthew);
        $cid = $this->enroll('Cid', 'Gamma', $this->matthew);
        $first = $this->grade($ana, 'matthew-MATH', [85, 86, 87, 88], 'submitted');
        $second = $this->grade($bea, 'matthew-MATH', [80, 55, 82, 84], 'submitted');
        $subject = $this->classes['matthew-MATH']['offering']->subject;
        $key = $this->matthew->public_id.':'.$subject->public_id;

        // Not opened yet: nothing is approved.
        $this->actingAs($this->registrar)->post(route('grading.admin.approve-classes'), ['classes' => [$key]])
            ->assertSessionHas('status', 'No grades were approved. 1 selected class still needs to be opened and reviewed first.');
        $this->assertSame(0, StudentSubjectGrade::query()->where('status', 'finalized')->count());

        // Opening the class (dialog request) shows every student and stores what was seen.
        $response = $this->actingAs($this->registrar)->get(route('grading.admin.class', [$this->matthew, $subject]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('Alpha, Ana')->assertSee('Gamma, Cid')->assertSee('A quarter is below 60')->assertSee('Approve all 2')
            ->assertDontSee('Sign out');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertCount(2, session(AdminGradeController::REVIEWED.'.'.$key));
        $this->assertDatabaseHas('audit_logs', ['action' => 'grading.admin.class_reviewed', 'actor_user_id' => $this->registrar->id]);

        // After review: one grade changes and a new one is submitted. Only the unchanged reviewed grade is approved.
        $second->update(['quarter_2' => 81, 'final_grade' => intdiv(80 + 81 + 82 + 84 + 2, 4)]);
        $late = $this->grade($cid, 'matthew-MATH', [88, 88, 88, 88], 'submitted');
        $this->actingAs($this->registrar)->post(route('grading.admin.approve-classes'), ['classes' => [$key]])
            ->assertSessionHas('status', 'Approved 1 final grade in 1 class. 1 grade was skipped because it changed after you opened the class. Open it again to review.');
        $this->assertSame('finalized', $first->fresh()->status);
        $this->assertSame('submitted', $second->fresh()->status);
        $this->assertSame('submitted', $late->fresh()->status);
        $this->assertNull(session(AdminGradeController::REVIEWED.'.'.$key));
        $this->assertDatabaseHas('audit_logs', ['action' => 'grading.finalized', 'actor_user_id' => $this->registrar->id]);

        // Without JavaScript the same link is a full page.
        $this->actingAs($this->registrar)->get(route('grading.admin.class', [$this->matthew, $subject]))->assertOk()
            ->assertSee('Sign out')->assertSee('Approve all 2');
        $this->actingAs($this->registrar)->post(route('grading.admin.approve-classes'), ['classes' => ['not-a-class']])
            ->assertSessionHasErrors('classes.0');
    }

    public function test_review_all_marks_the_shown_classes_reviewed_and_skips_stale_grades(): void
    {
        $ana = $this->enroll('Ana', 'Alpha', $this->matthew);
        $bea = $this->enroll('Bea', 'Beta', $this->matthew);
        $dan = $this->enroll('Dan', 'Delta', $this->mark);
        $first = $this->grade($ana, 'matthew-MATH', [85, 86, 87, 88], 'submitted');
        $second = $this->grade($bea, 'matthew-MATH', [80, 55, 82, 84], 'submitted');
        $third = $this->grade($dan, 'mark-MATH', [90, 91, 92, 93], 'submitted');
        $math = $this->classes['matthew-MATH']['offering']->subject;
        $matthewKey = $this->matthew->public_id.':'.$math->public_id;
        $markKey = $this->mark->public_id.':'.$math->public_id;
        $seen = fn (StudentSubjectGrade $grade): string => $grade->public_id.':'.GradeFingerprint::of($grade->fresh());

        // The list shows every waiting final grade at a glance, and the Review all form carries exactly those grades.
        $this->actingAs($this->registrar)->get(route('grading.admin.index'))->assertOk()
            ->assertSeeText('Alpha, A.')->assertSeeText('Beta, B.')->assertSeeText('Delta, D.')
            ->assertSee('Review all on this page (2)')
            ->assertSee('value="'.$seen($first).'"', false)
            ->assertSee('value="'.$seen($third).'"', false);

        $fingerprints = [$matthewKey => [$seen($first), $seen($second)], $markKey => [$seen($third)]];
        // Bea's grade changes after the page was shown: it is not included.
        $second->update(['quarter_2' => 81, 'final_grade' => intdiv(80 + 81 + 82 + 84 + 2, 4)]);

        $this->actingAs($this->registrar)->post(route('grading.admin.review-classes'), ['seen' => $fingerprints])
            ->assertSessionHas('status', 'Marked 2 classes (2 grades) as reviewed. They are selected below; choose Approve selected to approve them. 1 grade changed or left the queue after the page loaded and was not included.')
            ->assertSessionHas('grading_preselect', [$matthewKey, $markKey]);
        $this->assertSame([$first->public_id], array_keys(session(AdminGradeController::REVIEWED.'.'.$matthewKey)));
        $this->assertDatabaseHas('audit_logs', ['action' => 'grading.admin.classes_reviewed', 'actor_user_id' => $this->registrar->id]);
        $this->assertSame(0, StudentSubjectGrade::query()->where('status', 'finalized')->count());

        $this->actingAs($this->registrar)->post(route('grading.admin.approve-classes'), ['classes' => [$matthewKey, $markKey]])
            ->assertSessionHas('status', 'Approved 2 final grades in 2 classes.');
        $this->assertSame('finalized', $first->fresh()->status);
        $this->assertSame('finalized', $third->fresh()->status);
        $this->assertSame('submitted', $second->fresh()->status);

        $this->actingAs($this->registrar)->post(route('grading.admin.review-classes'), ['seen' => ['bad-key' => [$seen($second)]]])
            ->assertSessionHasErrors('seen');
        $this->actingAs($this->registrar)->post(route('grading.admin.review-classes'), ['seen' => [$matthewKey => ['not-a-fingerprint']]])
            ->assertSessionHasErrors('seen.'.$matthewKey.'.0');
        $this->actingAs($this->mathTeacher)->post(route('grading.admin.review-classes'), ['seen' => [$matthewKey => [$seen($second)]]])
            ->assertForbidden();
    }

    public function test_class_review_and_bulk_approval_are_denied_to_other_roles(): void
    {
        $subject = $this->classes['matthew-MATH']['offering']->subject;
        $accounting = $this->user('accounting', 'Acct User');
        foreach ([$this->mathTeacher, $accounting] as $user) {
            $this->actingAs($user)->get(route('grading.admin.class', [$this->matthew, $subject]))->assertForbidden();
            $this->actingAs($user)->post(route('grading.admin.approve-classes'), ['classes' => [$this->matthew->public_id.':'.$subject->public_id]])->assertForbidden();
            $this->actingAs($user)->get(route('grading.admin.index'))->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        $this->get(route('grading.admin.class', [$this->matthew, $subject]))->assertRedirect(route('identity.login'));

        // A subject not offered in the section, or a preschool section, is not a gradable class.
        $science = Subject::query()->create(['code' => 'SCI', 'name' => 'Science', 'is_active' => true]);
        $this->actingAs($this->registrar)->get(route('grading.admin.class', [$this->matthew, $science]))->assertNotFound();
        $kinder = GradeLevel::query()->create(['code' => 'SK', 'name' => 'Senior Kinder', 'sequence' => 3, 'is_active' => true]);
        $kinderSection = Section::query()->create(['academic_year_id' => $this->year->id, 'grade_level_id' => $kinder->id, 'name' => 'Joy', 'capacity' => 15, 'status' => 'active']);
        SectionSubject::query()->create(['section_id' => $kinderSection->id, 'subject_id' => $subject->id]);
        $this->actingAs($this->registrar)->get(route('grading.admin.class', [$kinderSection, $subject]))->assertNotFound();
    }

    /** @return array{offering: SectionSubject, assignment: TeachingAssignment} */
    private function offer(Section $section, Subject $subject, User $teacher): array
    {
        $staff = StaffProfile::query()->firstOrCreate(['user_id' => $teacher->id], ['employee_number' => '26-JCA-'.(300 + StaffProfile::query()->count()), 'active_from' => '2026-01-01']);
        $offering = SectionSubject::query()->create(['section_id' => $section->id, 'subject_id' => $subject->id]);

        return [
            'offering' => $offering->load('subject'),
            'assignment' => TeachingAssignment::query()->create(['staff_profile_id' => $staff->id, 'section_id' => $section->id, 'section_subject_id' => $offering->id, 'assignment_type' => 'subject_teacher', 'is_active' => true]),
        ];
    }

    private function enroll(string $first, string $last, Section $section): Enrollment
    {
        $student = StudentProfile::query()->create(['first_name' => $first, 'last_name' => $last]);
        $guardian = GuardianProfile::query()->create(['first_name' => 'Fictional', 'last_name' => 'Guardian']);
        $enrollment = Enrollment::query()->create([
            'application_number' => 'GA-'.Str::upper(Str::random(10)), 'student_id' => $student->id,
            'academic_year_id' => $this->year->id, 'grade_level_id' => $section->grade_level_id, 'applicant_guardian_id' => $guardian->id,
            'student_category' => 'regular', 'status' => 'enrolled', 'active_enrollment_key' => $student->id.':'.$this->year->id,
            'contact_verified_at' => now(), 'approved_at' => now(), 'enrolled_at' => now(),
        ]);
        SectionAssignment::query()->create(['enrollment_id' => $enrollment->id, 'section_id' => $section->id, 'status' => 'confirmed', 'active_assignment_key' => (string) $enrollment->id, 'confirmed_by' => $this->registrar->id, 'confirmed_at' => now(), 'effective_from' => today()]);

        return $enrollment;
    }

    /** @param list<int|null> $quarters */
    private function grade(Enrollment $enrollment, string $class, array $quarters, string $status): StudentSubjectGrade
    {
        return $this->rawGrade($enrollment, $this->classes[$class], $quarters, $status);
    }

    /**
     * @param  array{offering: SectionSubject, assignment: TeachingAssignment}  $class
     * @param  list<int|null>  $quarters
     */
    private function rawGrade(Enrollment $enrollment, array $class, array $quarters, string $status): StudentSubjectGrade
    {
        $complete = ! in_array(null, $quarters, true);

        return StudentSubjectGrade::query()->create([
            'enrollment_id' => $enrollment->id, 'subject_id' => $class['offering']->subject_id, 'section_subject_id' => $class['offering']->id,
            'teaching_assignment_id' => $class['assignment']->id, 'subject_code_snapshot' => $class['offering']->subject->code, 'subject_name_snapshot' => $class['offering']->subject->name,
            'quarter_1' => $quarters[0], 'quarter_2' => $quarters[1], 'quarter_3' => $quarters[2], 'quarter_4' => $quarters[3],
            'final_grade' => $complete ? intdiv(array_sum($quarters) + 2, 4) : null, 'status' => $status,
            'submitted_by' => $status === 'draft' ? null : $this->mathTeacher->id, 'submitted_at' => $status === 'draft' ? null : now(),
            'finalized_by' => $status === 'finalized' ? $this->registrar->id : null, 'finalized_at' => $status === 'finalized' ? now() : null,
        ]);
    }

    private function user(string $role, string $name): User
    {
        $user = User::query()->create([
            'name' => $name, 'email' => Str::lower(Str::random(12)).'@example.test', 'password' => Hash::make('Password12345'),
            'status' => AccountStatus::Active, 'password_changed_at' => now(),
        ]);

        return app(AssignRolesAction::class)->execute($user, [$role], null);
    }
}
