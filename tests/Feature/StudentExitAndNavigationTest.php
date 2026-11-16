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

/**
 * UX overhaul: learner Transfer Out (SF10) and Drop (DepEd LIS), exit status
 * display, staff-only finance navigation, and the Back navigation fallback.
 */
class StudentExitAndNavigationTest extends TestCase
{
    use RefreshDatabase;

    private static int $gradeSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        self::$gradeSequence = 0;
    }

    public function test_registrar_transfers_out_a_learner_with_sf10_release_details(): void
    {
        $registrar = $this->user(['registrar']);
        $student = $this->learner(['first_name' => 'Maricar', 'last_name' => 'Santino']);
        $enrollment = $this->enrollmentFor($student, 'enrolled');

        $this->actingAs($registrar)
            ->post(route('people.registrar.students.transfer-out', $student), [
                'receiving_school' => 'Bolinao Central School',
                'receiving_school_id' => '101234',
                'effective_on' => today()->toDateString(),
                'sf10_release_method' => 'mail',
                'sf10_released_on' => today()->toDateString(),
                'reason' => 'Family relocated to another town.',
                'confirm_transfer' => '1',
            ])
            ->assertRedirect(route('people.registrar.students.show', $student))
            ->assertSessionHasNoErrors();

        $student->refresh();
        $this->assertSame('transferred_out', $student->status);
        $this->assertNotNull($student->archived_at);
        $this->assertNotNull($student->transferred_out_at);
        $this->assertStringContainsString('Bolinao Central School', (string) $student->archive_reason);
        $this->assertStringContainsString('SF10', (string) $student->archive_reason);
        // D-066: the transfer is also kept as data for the retention report.
        $this->assertSame('transferred_out', $student->exit_category);
        $this->assertSame('Bolinao Central School', $student->exit_receiving_school);

        // Enrollment history is preserved untouched.
        $this->assertSame('enrolled', $enrollment->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $registrar->id,
            'action' => 'people.student.transferred_out',
            'subject_id' => $student->id,
        ]);
    }

    public function test_hand_carried_sf10_requires_the_carrier_name_and_errors_use_their_own_bag(): void
    {
        $registrar = $this->user(['registrar']);
        $student = $this->learner(['first_name' => 'Maria', 'last_name' => 'Santos']);

        $this->actingAs($registrar)
            ->post(route('people.registrar.students.transfer-out', $student), [
                'receiving_school' => 'Far Away National High School',
                'effective_on' => today()->toDateString(),
                'sf10_release_method' => 'hand_carried',
                'sf10_released_on' => today()->toDateString(),
                'reason' => 'Moving to Mindanao with family.',
                'confirm_transfer' => '1',
            ])
            ->assertSessionHasErrors(['sf10_released_to'], null, 'transferOut');

        $this->assertSame('active', $student->fresh()->status);
    }

    public function test_drop_requires_deped_lis_confirmation_then_marks_the_learner_dropped(): void
    {
        $registrar = $this->user(['registrar']);
        $student = $this->learner(['first_name' => 'Jose', 'last_name' => 'Rizal']);
        $payload = [
            'effective_on' => today()->toDateString(),
            'reason' => 'Learner stopped attending classes.',
            'confirm_drop' => '1',
        ];

        // The kind of drop is required (D-066).
        $this->actingAs($registrar)
            ->post(route('people.registrar.students.drop', $student), $payload + ['lis_dropped' => '1'])
            ->assertSessionHasErrors(['drop_category'], null, 'dropStudent');
        $payload['drop_category'] = 'excluded';

        $this->actingAs($registrar)
            ->post(route('people.registrar.students.drop', $student), $payload)
            ->assertSessionHasErrors(['lis_dropped'], null, 'dropStudent');
        $this->assertSame('active', $student->fresh()->status);

        $this->actingAs($registrar)
            ->post(route('people.registrar.students.drop', $student), $payload + ['lis_dropped' => '1'])
            ->assertRedirect(route('people.registrar.students.show', $student));

        $this->assertSame('dropped', $student->fresh()->status);
        $this->assertSame('excluded', $student->fresh()->exit_category);
        $this->assertSame(today()->toDateString(), $student->fresh()->dropped_at->toDateString());
        $this->assertNotNull($student->fresh()->archived_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'people.student.dropped', 'subject_id' => $student->id]);
    }

    public function test_an_archived_learner_cannot_be_exited_twice(): void
    {
        $registrar = $this->user(['registrar']);
        $student = $this->learner(['first_name' => 'Ana', 'last_name' => 'Cruz', 'status' => 'dropped', 'archived_at' => now()]);

        $this->actingAs($registrar)
            ->post(route('people.registrar.students.drop', $student), [
                'effective_on' => today()->toDateString(),
                'reason' => 'Trying to drop the learner again.',
                'drop_category' => 'dropped_out',
                'lis_dropped' => '1',
                'confirm_drop' => '1',
            ])
            ->assertSessionHasErrors(['reason'], null, 'dropStudent');
    }

    public function test_non_registrar_staff_cannot_transfer_out_or_drop(): void
    {
        $teacher = $this->user(['teacher']);
        $student = $this->learner(['first_name' => 'Maria', 'last_name' => 'Santos']);

        $this->actingAs($teacher)
            ->post(route('people.registrar.students.drop', $student), [])
            ->assertForbidden();
        $this->actingAs($teacher)
            ->post(route('people.registrar.students.transfer-out', $student), [])
            ->assertForbidden();
        $this->assertSame('active', $student->fresh()->status);
    }

    public function test_archived_directory_shows_transferred_out_and_dropped_instead_of_enrolled(): void
    {
        $registrar = $this->user(['registrar']);
        $transferred = $this->learner(['first_name' => 'Kathleen', 'last_name' => 'Datuin', 'status' => 'transferred_out', 'archived_at' => now()]);
        $this->enrollmentFor($transferred, 'enrolled');
        $dropped = $this->learner(['first_name' => 'Pedro', 'last_name' => 'Penduko', 'status' => 'dropped', 'archived_at' => now()]);
        $this->enrollmentFor($dropped, 'enrolled');

        $response = $this->actingAs($registrar)
            ->get(route('people.registrar.students.index', ['archive' => 1]))
            ->assertOk()
            ->assertSee('Kathleen')
            ->assertSee('Transferred Out')
            ->assertSee('Dropped');

        $this->assertStringNotContainsString('jca-status-ready', (string) $response->getContent());

        $this->actingAs($registrar)
            ->get(route('people.registrar.students.index', ['archive' => 1, 'exit' => 'dropped']))
            ->assertOk()
            ->assertSee('Penduko')
            ->assertDontSee('Kathleen');

        // Exited learners are not listed among active students.
        $this->actingAs($registrar)
            ->get(route('people.registrar.students.index'))
            ->assertOk()
            ->assertDontSee('Penduko');
    }

    public function test_learner_page_offers_transfer_and_drop_and_a_back_link_to_the_directory(): void
    {
        $registrar = $this->user(['registrar']);
        $student = $this->learner(['first_name' => 'Maria', 'last_name' => 'Santos']);

        $this->actingAs($registrar)
            ->get(route('people.registrar.students.show', $student))
            ->assertOk()
            ->assertSee('Mark as Transferred Out')
            ->assertSee('Mark as Dropped')
            ->assertSee('SF10')
            ->assertSee('DepEd LIS')
            ->assertSee('data-jca-back', false)
            ->assertSee('data-fallback-url="'.route('people.registrar.students.index').'"', false)
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('jca-location__crumb--current', false)
            ->assertSeeInOrder(['Dashboard', 'Student directory', 'Maria Santos']);
    }

    public function test_location_bar_highlights_the_current_module_and_dashboard(): void
    {
        $registrar = $this->user(['registrar']);

        $this->actingAs($registrar)
            ->get(route('identity.dashboard'))
            ->assertOk()
            ->assertSee('data-jca-location', false)
            ->assertSee('jca-location__crumb--current', false);

        $response = $this->actingAs($registrar)
            ->get(route('people.registrar.students.index', ['archive' => 1]))
            ->assertOk();

        $html = (string) $response->getContent();
        $this->assertMatchesRegularExpression('/jca-location__crumb--current"[^>]*>\s*Student directory/', $html);
        $this->assertStringContainsString('href="'.route('identity.dashboard').'" class="jca-location__crumb" data-jca-restore', $html);
    }

    public function test_dashboard_has_no_back_control(): void
    {
        $this->actingAs($this->user(['registrar']))
            ->get(route('identity.dashboard'))
            ->assertOk()
            ->assertSee('data-jca-page-root', false)
            ->assertDontSee('data-jca-back', false);
    }

    public function test_students_and_guardians_do_not_see_or_reach_financial_records(): void
    {
        foreach (['student', 'guardian'] as $role) {
            $user = $this->user([$role]);

            $this->actingAs($user)
                ->get(route('identity.dashboard'))
                ->assertOk()
                ->assertDontSee('Financial records')
                ->assertSee('Student information');

            $this->actingAs($user)->get(route('billing.portal.index'))->assertForbidden();
        }

        $this->actingAs($this->user(['accounting']))
            ->get(route('identity.dashboard'))
            ->assertOk()
            ->assertSee('Financial records');
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
            'code' => 'EXT-'.$suffix,
            'name' => 'Exit Year '.$suffix,
            'starts_on' => '2026-06-01',
            'ends_on' => '2027-03-31',
            'status' => 'open',
        ]);
        $grade = GradeLevel::query()->create([
            'code' => 'EXT-G'.$sequence.'-'.$suffix,
            'name' => 'Grade '.$sequence.' '.$suffix,
            'sequence' => $sequence,
            'school_stage' => 'Junior High School',
            'is_active' => true,
        ]);
        $guardian = GuardianProfile::query()->create([
            'first_name' => 'Exit',
            'last_name' => 'Guardian '.$suffix,
            'email' => Str::lower($suffix).'@example.test',
        ]);

        return Enrollment::query()->create([
            'application_number' => 'EXT-'.$suffix,
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
            'name' => 'Exit '.Str::random(6),
            'email' => Str::lower(Str::random(12)).'@example.test',
            'password' => Hash::make('Password12345'),
            'status' => AccountStatus::Active,
            'password_changed_at' => now(),
        ]);

        return app(AssignRolesAction::class)->execute($user, $roles, null);
    }
}
