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
            'father_first_name' => 'Rodrigo',
            'father_last_name' => 'Santos',
            'mother_first_name' => 'Elena',
            'mother_maiden_last_name' => 'Cruz',
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
            ->assertSee('Rodrigo Santos')
            ->assertSee('Elena Cruz')
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

    public function test_registrar_can_correct_allowlisted_profile_fields_with_before_and_after_history(): void
    {
        $registrar = $this->user(['registrar']);
        $student = $this->learner([
            'first_name' => 'Mria',
            'last_name' => 'Santos',
            'religion' => 'Christianity',
            'is_4ps_recipient' => false,
            'is_learner_with_disability' => false,
        ]);

        $this->actingAs($registrar)
            ->put(route('people.registrar.students.update', $student), [
                'first_name' => 'Maria',
                'middle_name' => 'Cruz',
                'last_name' => 'Santos',
                'is_indigenous' => '0',
                'is_4ps_recipient' => '0',
                'is_learner_with_disability' => '0',
                'religion' => 'Changed in browser',
                'father_first_name' => 'Rodrigo',
                'father_last_name' => 'Santos',
                'reason' => 'Corrected from the original birth certificate.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $student->refresh();
        $this->assertSame('Maria', $student->first_name);
        $this->assertSame('Rodrigo Santos', $student->father_full_name);
        $this->assertSame('Christianity', $student->religion);
        $this->assertDatabaseHas('student_profile_corrections', [
            'student_id' => $student->id,
            'actor_user_id' => $registrar->id,
            'field_name' => 'first_name',
            'before_value' => 'Mria',
            'after_value' => 'Maria',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $registrar->id,
            'action' => 'people.student.profile_corrected',
            'subject_id' => $student->id,
        ]);
    }

    public function test_registrar_records_the_indigenous_peoples_group_with_the_same_question_as_the_application(): void
    {
        $registrar = $this->user(['registrar']);
        $student = $this->learner(['first_name' => 'Maria', 'last_name' => 'Santos', 'ip_group' => 'Ayta Mag-antsi']);
        $correct = fn (array $changes) => $this->actingAs($registrar)
            ->from(route('people.registrar.students.show', $student))
            ->put(route('people.registrar.students.update', $student), [
                'first_name' => 'Maria', 'last_name' => 'Santos', 'is_4ps_recipient' => '0', 'is_learner_with_disability' => '0',
                'reason' => 'Corrected from the learner\'s certificate of tribal membership.', ...$changes,
            ]);

        // A name typed before the list existed shows under "Other (please specify)".
        $this->actingAs($registrar)->get(route('people.registrar.students.show', $student))->assertOk()
            ->assertSee('name="is_indigenous" value="1" required checked', false)
            ->assertSee('<option value="other" selected>', false)
            ->assertSee('value="Ayta Mag-antsi"', false)
            ->assertDontSee('<input id="ip_group" name="ip_group"', false);

        $correct(['is_indigenous' => '1'])->assertSessionHasErrors('ip_group');
        $correct(['is_indigenous' => '1', 'ip_group' => 'Made-up group'])->assertSessionHasErrors('ip_group');
        $this->assertSame('Ayta Mag-antsi', $student->fresh()->ip_group);

        $correct(['is_indigenous' => '1', 'ip_group' => 'Aeta (Ayta)'])->assertSessionHasNoErrors();
        $this->assertSame('Aeta (Ayta)', $student->fresh()->ip_group);
        $this->assertDatabaseHas('student_profile_corrections', [
            'student_id' => $student->id, 'field_name' => 'ip_group', 'before_value' => 'Ayta Mag-antsi', 'after_value' => 'Aeta (Ayta)',
        ]);
        $this->assertDatabaseMissing('student_profile_corrections', ['field_name' => 'is_indigenous']);
        $this->assertDatabaseMissing('student_profile_corrections', ['field_name' => 'ip_group_other']);

        $correct(['is_indigenous' => '0', 'ip_group' => 'Aeta (Ayta)'])->assertSessionHasNoErrors();
        $this->assertNull($student->fresh()->ip_group);
        $this->actingAs($registrar)->get(route('people.registrar.students.show', $student))
            ->assertSee('name="is_indigenous" value="0" required checked', false);
    }

    public function test_profile_correction_requires_a_meaningful_reason(): void
    {
        $registrar = $this->user(['registrar']);
        $student = $this->learner(['first_name' => 'Maria', 'last_name' => 'Santos']);

        $this->actingAs($registrar)
            ->from(route('people.registrar.students.show', $student))
            ->put(route('people.registrar.students.update', $student), [
                'first_name' => 'Marie',
                'last_name' => 'Santos',
                'is_indigenous' => '0',
                'is_4ps_recipient' => '0',
                'is_learner_with_disability' => '0',
                'reason' => 'test',
            ])
            ->assertRedirect(route('people.registrar.students.show', $student))
            ->assertSessionHasErrors('reason');

        $this->assertSame('Maria', $student->fresh()->first_name);
        $this->assertDatabaseCount('student_profile_corrections', 0);
    }

    public function test_identifier_correction_is_separate_format_checked_and_unique(): void
    {
        $registrar = $this->user(['registrar']);
        $student = $this->learner(['first_name' => 'Maria', 'last_name' => 'Santos', 'student_number' => 'JCA-26-501']);
        $this->learner(['first_name' => 'Other', 'last_name' => 'Learner', 'student_number' => 'JCA-26-502', 'lrn' => '123456789012']);

        $this->actingAs($registrar)
            ->put(route('people.registrar.students.identifiers', $student), [
                'student_number' => 'jca-26-503',
                'lrn' => '109876543210',
                'reason' => 'Corrected against the official learner record.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('JCA-26-503', $student->fresh()->student_number);
        $this->assertDatabaseHas('student_profile_corrections', [
            'student_id' => $student->id,
            'field_name' => 'student_number',
            'before_value' => 'JCA-26-501',
            'after_value' => 'JCA-26-503',
        ]);

        $this->actingAs($registrar)
            ->put(route('people.registrar.students.identifiers', $student), [
                'student_number' => 'JCA-26-502',
                'lrn' => '123456789012',
                'reason' => 'Attempted duplicate should be rejected safely.',
            ])
            ->assertSessionHasErrors(['student_number', 'lrn']);
    }

    public function test_profile_correction_denies_unauthenticated_and_non_registrar_users(): void
    {
        $student = $this->learner(['first_name' => 'Maria', 'last_name' => 'Santos']);
        $payload = [
            'first_name' => 'Marie',
            'last_name' => 'Santos',
            'is_4ps_recipient' => '0',
            'is_learner_with_disability' => '0',
            'reason' => 'Correction should not be authorized for this actor.',
        ];

        $this->put(route('people.registrar.students.update', $student), $payload)
            ->assertRedirect(route('identity.login'));

        $this->actingAs($this->user(['teacher']))
            ->put(route('people.registrar.students.update', $student), $payload)
            ->assertForbidden();

        $this->assertSame('Maria', $student->fresh()->first_name);
    }

    public function test_registrar_corrects_previous_school_through_enrollment_owned_history(): void
    {
        $registrar = $this->user(['registrar']);
        $student = $this->learner(['first_name' => 'Maria', 'last_name' => 'Santos']);
        $enrollment = $this->enrollmentFor($student);
        $enrollment->update([
            'student_category' => 'transferee',
            'previous_school_name' => 'Old Schhol',
            'previous_school_address' => 'Lingayen',
        ]);

        $this->actingAs($registrar)
            ->put(route('enrollment.registrar.previous-school.update', $enrollment), [
                'previous_school_name' => 'Old School',
                'previous_school_address' => 'Lingayen, Pangasinan',
                'reason' => 'Corrected against the submitted school records.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Old School', $enrollment->fresh()->previous_school_name);
        $this->assertDatabaseHas('enrollment_corrections', [
            'enrollment_id' => $enrollment->id,
            'actor_user_id' => $registrar->id,
            'field_name' => 'previous_school_name',
            'before_value' => 'Old Schhol',
            'after_value' => 'Old School',
        ]);
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
