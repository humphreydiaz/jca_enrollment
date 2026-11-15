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
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StaffProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use App\Modules\StudentSupport\Models\ApprovedSupportSummary;
use App\Modules\StudentSupport\Models\SupportCase;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $otherTeacher;

    private User $registrar;

    private AcademicYear $pastYear;

    private AcademicYear $currentYear;

    private Section $matthew;

    private Section $love;

    private Section $oldSection;

    /** @var array<string, array{offering: SectionSubject, assignment: TeachingAssignment}> */
    private array $classes = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-06 09:00:00');
        $this->seed(RoleSeeder::class);
        $this->registrar = $this->user('registrar', 'Rita Registrar');
        $this->teacher = $this->user('teacher', 'Carlo Mendoza');
        $this->otherTeacher = $this->user('teacher', 'Joan Santos');
        $staff = StaffProfile::query()->create(['user_id' => $this->teacher->id, 'employee_number' => '26-JCA-100', 'active_from' => '2025-01-01']);
        $otherStaff = StaffProfile::query()->create(['user_id' => $this->otherTeacher->id, 'employee_number' => '26-JCA-101', 'active_from' => '2025-01-01']);

        $this->pastYear = AcademicYear::query()->create(['code' => '2025-2026', 'name' => 'School Year 2025-2026', 'starts_on' => '2025-06-09', 'ends_on' => '2026-04-08', 'status' => 'closed']);
        $this->currentYear = AcademicYear::query()->create(['code' => '2026-2027', 'name' => 'School Year 2026-2027', 'starts_on' => '2026-06-08', 'ends_on' => '2027-04-08', 'status' => 'open']);
        $grade3 = GradeLevel::query()->create(['code' => 'G3', 'name' => 'Grade 3', 'sequence' => 6, 'is_active' => true]);
        $grade6 = GradeLevel::query()->create(['code' => 'G6', 'name' => 'Grade 6', 'sequence' => 9, 'is_active' => true]);

        $this->matthew = Section::query()->create(['academic_year_id' => $this->currentYear->id, 'grade_level_id' => $grade6->id, 'name' => 'Matthew', 'capacity' => 15, 'status' => 'active']);
        $this->love = Section::query()->create(['academic_year_id' => $this->currentYear->id, 'grade_level_id' => $grade3->id, 'name' => 'Love', 'capacity' => 15, 'status' => 'active']);
        $mark = Section::query()->create(['academic_year_id' => $this->currentYear->id, 'grade_level_id' => $grade6->id, 'name' => 'Mark', 'capacity' => 15, 'status' => 'active']);
        $this->oldSection = Section::query()->create(['academic_year_id' => $this->pastYear->id, 'grade_level_id' => $grade6->id, 'name' => 'Bartholomew', 'capacity' => 15, 'status' => 'active']);

        $this->classes['MATH'] = $this->offer($this->matthew, 'MATH', 'Mathematics', $staff, ['days' => '1,2,3,4,5', 'starts_at' => '08:00', 'ends_at' => '08:45']);
        $this->classes['ENG'] = $this->offer($this->love, 'ENG', 'English', $staff);
        $this->classes['SCI'] = $this->offer($this->oldSection, 'SCI', 'Science', $staff);
        $this->offer($this->matthew, 'FIL', 'Filipino', $otherStaff);
        TeachingAssignment::query()->create(['staff_profile_id' => $staff->id, 'section_id' => $this->matthew->id, 'assignment_type' => 'adviser', 'is_active' => true]);
        TeachingAssignment::query()->create(['staff_profile_id' => $otherStaff->id, 'section_id' => $mark->id, 'assignment_type' => 'adviser', 'is_active' => true]);
    }

    public function test_my_teaching_opens_on_the_current_year_and_lists_each_section_once(): void
    {
        $zed = $this->enroll('Zed', 'Alpha', $this->matthew);
        $ana = $this->enroll('Ana', 'Beta', $this->matthew);
        $this->enroll('Pat', 'Former', $this->oldSection, $this->pastYear);
        $this->approvedSummary($ana, 'Seat near the board during lessons.');

        $page = $this->actingAs($this->teacher)->get(route('academics.teacher.assignments'))->assertOk();
        $page->assertSeeText('School Year 2026-2027')->assertSee('Current')->assertSeeText('School Year 2025-2026')
            ->assertSee('Grade 6 · Matthew')->assertSee('Grade 3 · Love')->assertSee('Adviser')
            ->assertDontSee('Bartholomew')->assertDontSee('Grade 6 · Mark')
            ->assertSeeInOrder(['Alpha, Zed', 'Beta, Ana'])
            ->assertSee('08:00 – 08:45')->assertSee('Joan Santos')
            ->assertSee(route('grading.teacher.show', $this->classes['MATH']['assignment']), false)
            ->assertDontSee('instructional support')
            ->assertDontSee('Seat near the board');
        // Adviser and subject-teacher roles in Matthew are merged: each student is listed once.
        $this->assertSame(1, substr_count($page->getContent(), 'Alpha, Zed'));
        $this->assertSame(1, substr_count($page->getContent(), '>Support notes<'));
        // Listing students never opens (or logs) a support summary.
        $this->assertSame(0, DB::table('support_access_logs')->count());
        $this->assertNotNull($zed);

        $this->actingAs($this->teacher)->get(route('academics.teacher.assignments', ['year' => $this->pastYear->public_id]))
            ->assertOk()->assertSee('Grade 6 · Bartholomew')->assertSee('Former, Pat')->assertDontSee('Grade 6 · Matthew');
    }

    public function test_my_teaching_validates_the_year_and_denies_other_roles(): void
    {
        $this->actingAs($this->teacher)->get(route('academics.teacher.assignments', ['year' => 'not-a-year']))
            ->assertRedirect()->assertSessionHasErrors('year');
        // A well-formed year the teacher has no classes in falls back to their default tab.
        $this->actingAs($this->teacher)->get(route('academics.teacher.assignments', ['year' => (string) Str::ulid()]))
            ->assertOk()->assertSee('Grade 6 · Matthew');

        $this->actingAs($this->registrar)->get(route('academics.teacher.assignments'))->assertForbidden();
        $this->actingAs($this->registrar)->get(route('grading.teacher.index'))->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->get(route('academics.teacher.assignments'))->assertRedirect(route('identity.login'));
        $this->get(route('grading.teacher.index'))->assertRedirect(route('identity.login'));
    }

    public function test_student_summary_loads_into_the_dialog_and_is_logged_only_when_opened(): void
    {
        $ana = $this->enroll('Ana', 'Beta', $this->matthew);
        $this->approvedSummary($ana, 'Seat near the board during lessons.');
        $url = route('teacher.students.support-summary', $ana->student);

        $fragment = $this->actingAs($this->teacher)->get($url, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee('Seat near the board during lessons.')->assertSee('Grade 6 · Matthew')
            ->assertDontSee('Sign out')->assertDontSee('<html', false);
        $this->assertStringContainsString('no-store', (string) $fragment->headers->get('Cache-Control'));
        $this->assertSame(1, DB::table('support_access_logs')->where('actor_user_id', $this->teacher->id)->count());

        // Without JavaScript the same link opens a full page.
        $this->actingAs($this->teacher)->get($url)->assertOk()->assertSee('Seat near the board during lessons.')->assertSee('Sign out')->assertSee('My teaching');

        // A student without notes shows a plain message; another teacher's student is not found.
        $zed = $this->enroll('Zed', 'Alpha', $this->matthew);
        $this->actingAs($this->teacher)->get(route('teacher.students.support-summary', $zed->student), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('No instructional support notes are on file');
        $unrelated = $this->user('teacher', 'Unrelated Teacher');
        StaffProfile::query()->create(['user_id' => $unrelated->id, 'employee_number' => '26-JCA-102', 'active_from' => '2025-01-01']);
        $this->actingAs($unrelated)->get(route('teacher.students.support-summary', $ana->student), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertNotFound();
        $this->assertSame(0, DB::table('support_access_logs')->where('actor_user_id', $unrelated->id)->count());
    }

    public function test_grades_page_groups_classes_by_year_and_section_with_progress(): void
    {
        $zed = $this->enroll('Zed', 'Alpha', $this->matthew);
        $ana = $this->enroll('Ana', 'Beta', $this->matthew);
        $this->enroll('Cee', 'Gamma', $this->matthew);
        $this->grade($zed, 'MATH', [88, 90, 91, 93], 'finalized');
        $this->grade($ana, 'MATH', [80, 82, 85, 87], 'draft');

        $this->actingAs($this->teacher)->get(route('grading.teacher.index'))->assertOk()
            ->assertSeeText('School Year 2026-2027')->assertSeeText('School Year 2025-2026')
            ->assertSeeInOrder(['Grade 3 · Love', 'English', 'No students yet', 'Grade 6 · Matthew', 'Mathematics', '1 of 3 approved'])
            ->assertSee('ready to submit')->assertSee('not started')
            ->assertDontSee('Filipino')->assertDontSee('Science');

        $this->actingAs($this->teacher)->get(route('grading.teacher.index', ['year' => $this->pastYear->public_id]))->assertOk()
            ->assertSee('Grade 6 · Bartholomew')->assertSee('Science')->assertDontSee('Mathematics');

        $this->actingAs($this->otherTeacher)->get(route('grading.teacher.index'))->assertOk()
            ->assertSee('Filipino')->assertDontSee('Mathematics');
    }

    /** @param array<string, string> $schedule */
    private function offer(Section $section, string $code, string $name, StaffProfile $staff, array $schedule = []): array
    {
        $subject = Subject::query()->firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        $offering = SectionSubject::query()->create(['section_id' => $section->id, 'subject_id' => $subject->id, ...$schedule]);

        return [
            'offering' => $offering,
            'assignment' => TeachingAssignment::query()->create(['staff_profile_id' => $staff->id, 'section_id' => $section->id, 'section_subject_id' => $offering->id, 'assignment_type' => 'subject_teacher', 'is_active' => true]),
        ];
    }

    private function enroll(string $first, string $last, Section $section, ?AcademicYear $year = null): Enrollment
    {
        $year ??= $this->currentYear;
        $student = StudentProfile::query()->create(['first_name' => $first, 'last_name' => $last]);
        $guardian = GuardianProfile::query()->create(['first_name' => 'Fictional', 'last_name' => 'Guardian']);
        $enrollment = Enrollment::query()->create([
            'application_number' => 'TW-'.Str::upper(Str::random(10)), 'student_id' => $student->id,
            'academic_year_id' => $year->id, 'grade_level_id' => $section->grade_level_id, 'applicant_guardian_id' => $guardian->id,
            'student_category' => 'regular', 'status' => 'enrolled', 'active_enrollment_key' => $student->id.':'.$year->id,
            'contact_verified_at' => now(), 'approved_at' => now(), 'enrolled_at' => now(),
        ]);
        SectionAssignment::query()->create(['enrollment_id' => $enrollment->id, 'section_id' => $section->id, 'status' => 'confirmed', 'active_assignment_key' => (string) $enrollment->id, 'confirmed_by' => $this->registrar->id, 'confirmed_at' => now(), 'effective_from' => today()]);

        return $enrollment;
    }

    private function approvedSummary(Enrollment $enrollment, string $text): void
    {
        $case = SupportCase::query()->create(['enrollment_id' => $enrollment->id, 'status' => 'reviewed', 'opened_by' => $this->registrar->id, 'opened_at' => now(), 'final_outcome' => 'approved']);
        ApprovedSupportSummary::query()->create(['support_case_id' => $case->id, 'summary' => 'Unapproved draft must stay hidden.']);
        ApprovedSupportSummary::query()->create(['support_case_id' => $case->id, 'summary' => $text, 'approved_by' => $this->registrar->id, 'approved_at' => now(), 'effective_from' => today()]);
    }

    /** @param list<int|null> $quarters */
    private function grade(Enrollment $enrollment, string $code, array $quarters, string $status): void
    {
        $class = $this->classes[$code];
        $complete = ! in_array(null, $quarters, true);
        StudentSubjectGrade::query()->create([
            'enrollment_id' => $enrollment->id, 'subject_id' => $class['offering']->subject_id, 'section_subject_id' => $class['offering']->id,
            'teaching_assignment_id' => $class['assignment']->id, 'subject_code_snapshot' => $code, 'subject_name_snapshot' => $class['offering']->subject->name,
            'quarter_1' => $quarters[0], 'quarter_2' => $quarters[1], 'quarter_3' => $quarters[2], 'quarter_4' => $quarters[3],
            'final_grade' => $complete ? intdiv(array_sum($quarters) + 2, 4) : null, 'status' => $status,
            'submitted_by' => $status === 'draft' ? null : $this->teacher->id, 'submitted_at' => $status === 'draft' ? null : now(),
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
