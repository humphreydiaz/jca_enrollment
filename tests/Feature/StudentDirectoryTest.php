<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentDirectoryTest extends TestCase
{
    use RefreshDatabase;

    /** Grade levels carry a unique sequence, so each fixture needs its own. */
    private static int $gradeSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        self::$gradeSequence = 0;
    }

    public function test_registrar_sees_every_learner_profile_in_the_directory(): void
    {
        $registrar = $this->user(['registrar']);
        $this->learner(['first_name' => 'Maria', 'last_name' => 'Santos', 'lrn' => '123456789012']);
        $this->learner(['first_name' => 'Jose', 'last_name' => 'Rizal']);

        $this->actingAs($registrar)
            ->get(route('people.registrar.students.index'))
            ->assertOk()
            ->assertSee('Student directory')
            ->assertSee('Maria Santos')
            ->assertSee('Jose Rizal')
            ->assertSee('123456789012');
    }

    public function test_directory_search_matches_name_lrn_and_application_number(): void
    {
        $registrar = $this->user(['registrar']);
        $maria = $this->learner(['first_name' => 'Maria', 'last_name' => 'Santos', 'lrn' => '123456789012']);
        $this->learner(['first_name' => 'Jose', 'last_name' => 'Rizal']);
        $enrollment = $this->enrollmentFor($maria);

        foreach (['Santos', '123456789012', $enrollment->application_number] as $term) {
            $this->actingAs($registrar)
                ->get(route('people.registrar.students.index', ['search' => $term]))
                ->assertOk()
                ->assertSee('Maria Santos')
                ->assertDontSee('Jose Rizal');
        }
    }

    public function test_directory_filters_by_grade_status_and_learner_flag(): void
    {
        $registrar = $this->user(['registrar']);
        $indigenous = $this->learner(['first_name' => 'Lumad', 'last_name' => 'Datu', 'ip_group' => 'Manobo']);
        $regular = $this->learner(['first_name' => 'Plain', 'last_name' => 'Learner']);
        $enrollment = $this->enrollmentFor($indigenous, 'approved');
        $this->enrollmentFor($regular, 'draft');

        $this->actingAs($registrar)
            ->get(route('people.registrar.students.index', ['flag' => 'ip']))
            ->assertOk()
            ->assertSee('Lumad Datu')
            ->assertDontSee('Plain Learner');

        $this->actingAs($registrar)
            ->get(route('people.registrar.students.index', ['status' => 'approved']))
            ->assertOk()
            ->assertSee('Lumad Datu')
            ->assertDontSee('Plain Learner');

        $this->actingAs($registrar)
            ->get(route('people.registrar.students.index', [
                'grade' => $enrollment->gradeLevel->public_id,
            ]))
            ->assertOk()
            ->assertSee('Lumad Datu')
            ->assertDontSee('Plain Learner');
    }

    public function test_directory_sorts_by_a_whitelisted_column_and_ignores_anything_else(): void
    {
        $registrar = $this->user(['registrar']);
        $this->learner(['first_name' => 'Ana', 'last_name' => 'Abad']);
        $this->learner(['first_name' => 'Zeno', 'last_name' => 'Zamora']);

        $ascending = $this->actingAs($registrar)
            ->get(route('people.registrar.students.index', ['sort' => 'name', 'direction' => 'asc']))
            ->assertOk()
            ->getContent();
        $this->assertLessThan(strpos($ascending, 'Zeno Zamora'), strpos($ascending, 'Ana Abad'));

        $descending = $this->actingAs($registrar)
            ->get(route('people.registrar.students.index', ['sort' => 'name', 'direction' => 'desc']))
            ->assertOk()
            ->getContent();
        $this->assertLessThan(strpos($descending, 'Ana Abad'), strpos($descending, 'Zeno Zamora'));

        // An unknown sort column must fall back rather than reach the query builder.
        $this->actingAs($registrar)
            ->get(route('people.registrar.students.index', ['sort' => 'religion; drop table students']))
            ->assertOk();
    }

    public function test_registrar_reads_the_full_enrollment_form_detail_on_a_learner_record(): void
    {
        $registrar = $this->user(['registrar']);
        $student = $this->learner([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'religion' => 'Roman Catholic',
            'ip_group' => 'Manobo',
            'mother_tongue' => 'Cebuano',
            'is_4ps_recipient' => true,
            'four_ps_household_id' => 'HH-9912',
            'father_name' => 'Santos, Rodrigo',
            'mother_maiden_name' => 'Cruz, Elena',
            'current_address' => '12 Mabini Street, Butuan City',
        ]);
        $this->enrollmentFor($student);

        $this->actingAs($registrar)
            ->get(route('people.registrar.students.show', $student))
            ->assertOk()
            ->assertSee('Roman Catholic')
            ->assertSee('Manobo')
            ->assertSee('Cebuano')
            ->assertSee('HH-9912')
            ->assertSee('Santos, Rodrigo')
            ->assertSee('Cruz, Elena')
            ->assertSee('12 Mabini Street, Butuan City');
    }

    public function test_opening_a_learner_record_is_written_to_the_audit_trail(): void
    {
        $registrar = $this->user(['registrar']);
        $student = $this->learner(['first_name' => 'Maria', 'last_name' => 'Santos']);

        $this->actingAs($registrar)
            ->get(route('people.registrar.students.show', $student))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $registrar->id,
            'action' => 'people.student.record_viewed',
            'subject_id' => $student->id,
        ]);
    }

    public function test_an_ajax_request_returns_the_record_fragment_without_the_page_shell(): void
    {
        $registrar = $this->user(['registrar']);
        $student = $this->learner(['first_name' => 'Maria', 'last_name' => 'Santos', 'religion' => 'Iglesia ni Cristo']);

        $response = $this->actingAs($registrar)
            ->get(route('people.registrar.students.show', $student), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('Iglesia ni Cristo');

        $this->assertStringNotContainsString('<html', $response->getContent());
    }

    public function test_staff_without_the_registrar_role_cannot_reach_the_directory(): void
    {
        $student = $this->learner(['first_name' => 'Maria', 'last_name' => 'Santos']);

        foreach (['teacher', 'accounting', 'guardian'] as $role) {
            $this->actingAs($this->user([$role]))
                ->get(route('people.registrar.students.index'))
                ->assertForbidden();

            $this->actingAs($this->user([$role]))
                ->get(route('people.registrar.students.show', $student))
                ->assertForbidden();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function learner(array $attributes): StudentProfile
    {
        return StudentProfile::query()->create(array_merge([
            'student_number' => 'STU-'.Str::upper(Str::random(6)),
            'birth_date' => '2012-05-04',
            'sex' => 'female',
            'status' => 'active',
        ], $attributes));
    }

    private function enrollmentFor(StudentProfile $student, string $status = 'submitted'): Enrollment
    {
        $suffix = Str::upper(Str::random(8));
        $sequence = ++self::$gradeSequence;
        $year = AcademicYear::query()->create([
            'code' => 'DIR-'.$suffix,
            'name' => 'Directory Year '.$suffix,
            'starts_on' => '2026-06-01',
            'ends_on' => '2027-03-31',
            'status' => 'open',
        ]);
        $grade = GradeLevel::query()->create([
            'code' => 'DIR-G'.$sequence.'-'.$suffix,
            'name' => 'Grade '.$sequence.' '.$suffix,
            'sequence' => $sequence,
            'school_stage' => 'Junior High School',
            'is_active' => true,
        ]);
        $guardian = GuardianProfile::query()->create([
            'first_name' => 'Directory',
            'last_name' => 'Guardian '.$suffix,
            'email' => Str::lower($suffix).'@example.test',
        ]);

        return Enrollment::query()->create([
            'application_number' => 'DIR-'.$suffix,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'grade_level_id' => $grade->id,
            'applicant_guardian_id' => $guardian->id,
            'student_category' => 'regular',
            'guardian_relationship' => 'mother',
            'status' => $status,
            'submitted_at' => now(),
            'active_enrollment_key' => $student->id.':'.$year->id,
        ]);
    }

    /**
     * @param  list<string>  $roles
     */
    private function user(array $roles): User
    {
        $user = User::query()->create([
            'name' => 'Directory '.Str::random(6),
            'email' => Str::lower(Str::random(12)).'@example.test',
            'password' => Hash::make('Password12345'),
            'status' => AccountStatus::Active,
            'password_changed_at' => now(),
        ]);

        return app(AssignRolesAction::class)->execute($user, $roles, null);
    }
}
