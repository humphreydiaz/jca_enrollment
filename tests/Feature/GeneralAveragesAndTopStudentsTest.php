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
use App\Modules\People\Models\StaffProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/** D-069: general averages report and top students (final and projected). */
class GeneralAveragesAndTopStudentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AcademicYear $year;

    private GradeLevel $grade;

    private Section $faith;

    private Section $hope;

    /** @var array<string, SectionSubject> keyed "section.subject" */
    private array $offerings = [];

    private StaffProfile $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = $this->user(['admin']);
        $this->year = AcademicYear::query()->create(['code' => 'TS-'.Str::random(4), 'name' => 'School Year 2026-2027', 'starts_on' => '2026-06-01', 'ends_on' => '2027-03-31', 'status' => 'open']);
        $this->grade = GradeLevel::query()->create(['code' => 'TS-G5', 'name' => 'Grade 5', 'sequence' => 9205, 'is_active' => true]);
        $this->faith = Section::query()->create(['academic_year_id' => $this->year->id, 'grade_level_id' => $this->grade->id, 'name' => 'Faith', 'capacity' => 40, 'status' => 'active']);
        $this->hope = Section::query()->create(['academic_year_id' => $this->year->id, 'grade_level_id' => $this->grade->id, 'name' => 'Hope', 'capacity' => 40, 'status' => 'active']);
        $this->staff = StaffProfile::query()->create(['user_id' => $this->user(['teacher'])->id, 'employee_number' => 'TS-T1']);
        foreach ([$this->faith, $this->hope] as $section) {
            foreach (['MATH' => 'Mathematics', 'ENG' => 'English'] as $code => $name) {
                $subject = Subject::query()->firstOrCreate(['code' => 'TS-'.$code], ['name' => $name]);
                $this->offerings[$section->name.'.'.$code] = SectionSubject::query()->create(['section_id' => $section->id, 'subject_id' => $subject->id, 'status' => 'active']);
            }
        }
    }

    public function test_general_averages_rank_learners_and_filter_by_range_without_renumbering(): void
    {
        $this->learner('Ana', 'Top', $this->faith, ['MATH' => 96, 'ENG' => 94]);      // 95.00
        $this->learner('Ben', 'Mid', $this->faith, ['MATH' => 88, 'ENG' => 86]);      // 87.00
        $this->learner('Cara', 'Hope', $this->hope, ['MATH' => 90, 'ENG' => 90]);     // 90.00
        $this->learner('Dan', 'Draft', $this->hope, ['MATH' => 99], 'draft');           // never counted

        $page = $this->actingAs($this->user(['registrar']))->get(route('reporting.show', ['averages', 'sort' => 'general_average', 'direction' => 'desc']));
        $page->assertOk()->assertSeeInOrder(['Top, Ana', '95.00', 'Hope, Cara', '90.00', 'Mid, Ben', '87.00'])->assertDontSee('Draft, Dan');

        // A range filter keeps the rank from the whole grade (Ben stays 3rd in Grade 5, 2nd in Faith).
        $filtered = $this->actingAs($this->admin)->get(route('reporting.show', ['averages', 'band' => '85_89', 'cols' => ['student_name', 'general_average', 'rank_in_section', 'rank_in_grade']]));
        $filtered->assertOk()->assertSee('Mid, Ben')->assertDontSee('Top, Ana')->assertSeeInOrder(['Mid, Ben', '87.00', '2', '3']);

        // A teacher sees only sections they teach.
        $teacher = $this->user(['teacher']);
        $staff = StaffProfile::query()->create(['user_id' => $teacher->id, 'employee_number' => 'TS-T2']);
        TeachingAssignment::query()->create(['staff_profile_id' => $staff->id, 'section_id' => $this->hope->id, 'assignment_type' => 'adviser', 'is_active' => true]);
        $this->actingAs($teacher)->get(route('reporting.show', 'averages'))->assertOk()->assertSee('Hope, Cara')->assertDontSee('Top, Ana');

        $this->actingAs($this->user(['accounting']))->get(route('reporting.show', 'averages'))->assertForbidden();
    }

    public function test_top_students_rank_complete_learners_with_ties_and_list_incomplete_ones(): void
    {
        $this->learner('Ana', 'Tie', $this->faith, ['MATH' => 92, 'ENG' => 92]);
        $this->learner('Ben', 'Tie', $this->hope, ['MATH' => 94, 'ENG' => 90]);
        $this->learner('Cara', 'Third', $this->faith, ['MATH' => 85, 'ENG' => 85]);
        $this->learner('Dan', 'Partial', $this->faith, ['MATH' => 99]); // English not approved yet

        $this->actingAs($this->user(['registrar']))->get(route('reporting.top-students', ['year' => $this->year->public_id]))
            ->assertOk()
            ->assertSee('3 ranked · 1 not yet complete')
            ->assertSeeInOrder(['>1<', 'Ana Tie', '92.00', '>1<', 'Ben Tie', '92.00', '>3<', 'Cara Third'], false)
            ->assertDontSee('Dan Partial');

        $this->actingAs($this->admin)->get(route('reporting.top-students', ['group' => 'section']))
            ->assertOk()->assertSee('Grade 5 – Faith')->assertSee('Grade 5 – Hope');
    }

    public function test_only_ten_places_are_listed_per_group(): void
    {
        foreach (range(1, 12) as $i) {
            $this->learner('Learner', sprintf('No%02d', $i), $this->faith, ['MATH' => 100 - $i, 'ENG' => 100 - $i]);
        }

        $page = $this->actingAs($this->admin)->get(route('reporting.top-students'));
        $page->assertOk()->assertSee('Learner No10')->assertDontSee('Learner No11')->assertDontSee('Learner No12');
    }

    public function test_projected_view_uses_submitted_quarter_grades_but_never_drafts_and_is_for_admin_and_registrar(): void
    {
        $this->learner('Sub', 'Mitted', $this->faith, ['MATH' => [88, 90], 'ENG' => [92]], 'submitted');
        $this->learner('Dra', 'Fted', $this->faith, ['MATH' => [99], 'ENG' => [99]], 'draft');

        $this->actingAs($this->user(['registrar']))->get(route('reporting.top-students', ['basis' => 'projected']))
            ->assertOk()->assertSee('not yet approved by the Registrar')
            ->assertSee('Sub Mitted')->assertSee('90.50')->assertDontSee('Dra Fted');
        $this->actingAs($this->admin)->get(route('reporting.top-students', ['basis' => 'final']))->assertOk()->assertDontSee('Sub Mitted');

        auth()->logout();
        $this->get(route('reporting.top-students'))->assertRedirect(route('identity.login'));
        foreach (['teacher', 'accounting', 'guardian'] as $role) {
            $this->actingAs($this->user([$role]))->get(route('reporting.top-students'))->assertForbidden();
        }
    }

    /**
     * @param  array<string, int|list<int>>  $grades  subject code => final grade, or quarter grades when not finalized
     */
    private function learner(string $first, string $last, Section $section, array $grades, string $status = 'finalized'): Enrollment
    {
        $student = StudentProfile::query()->create(['student_number' => 'JCA-26-'.random_int(100, 999).Str::random(2), 'first_name' => $first, 'last_name' => $last]);
        $enrollment = Enrollment::query()->create(['application_number' => 'APP-'.Str::random(10), 'student_id' => $student->id, 'academic_year_id' => $this->year->id, 'grade_level_id' => $this->grade->id, 'student_category' => 'regular', 'status' => 'enrolled', 'active_enrollment_key' => $student->id.':'.$this->year->id, 'submitted_at' => now(), 'approved_at' => now(), 'enrolled_at' => now()]);
        SectionAssignment::query()->create(['enrollment_id' => $enrollment->id, 'section_id' => $section->id, 'status' => 'confirmed', 'active_assignment_key' => (string) $enrollment->id, 'confirmed_by' => $this->admin->id, 'confirmed_at' => now(), 'effective_from' => today()]);
        foreach ($grades as $code => $value) {
            $offering = $this->offerings[$section->name.'.'.$code];
            $assignment = TeachingAssignment::query()->firstOrCreate(['staff_profile_id' => $this->staff->id, 'section_id' => $section->id, 'section_subject_id' => $offering->id], ['assignment_type' => 'subject_teacher', 'is_active' => true]);
            $quarters = is_array($value) ? $value : [$value, $value, $value, $value];
            StudentSubjectGrade::query()->create([
                'enrollment_id' => $enrollment->id, 'subject_id' => $offering->subject_id, 'section_subject_id' => $offering->id, 'teaching_assignment_id' => $assignment->id,
                'subject_code_snapshot' => $code, 'subject_name_snapshot' => $code,
                'quarter_1' => $quarters[0] ?? null, 'quarter_2' => $quarters[1] ?? null, 'quarter_3' => $quarters[2] ?? null, 'quarter_4' => $quarters[3] ?? null,
                'final_grade' => $status === 'finalized' ? $value : null,
                'status' => $status,
                'finalized_by' => $status === 'finalized' ? $this->admin->id : null, 'finalized_at' => $status === 'finalized' ? now() : null,
            ]);
        }

        return $enrollment;
    }

    /** @param list<string> $roles */
    private function user(array $roles): User
    {
        $user = User::query()->create(['name' => 'Fictional '.Str::random(6), 'email' => Str::lower(Str::random(12)).'@example.test', 'password' => Hash::make('Password12345'), 'status' => AccountStatus::Active, 'password_changed_at' => now()]);

        return app(AssignRolesAction::class)->execute($user, $roles, null);
    }
}
