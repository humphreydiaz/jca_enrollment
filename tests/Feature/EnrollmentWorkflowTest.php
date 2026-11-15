<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Enrollment\Actions\TransitionEnrollmentAction;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Requirements\Models\DocumentType;
use App\Modules\StudentSupport\Actions\CreateSupportCaseAction;
use App\Modules\StudentSupport\Models\SupportCase;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EnrollmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;

    private GradeLevel $grade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->year = AcademicYear::query()->create([
            'code' => '2026-T', 'name' => 'Fictional 2026', 'starts_on' => '2026-06-01',
            'ends_on' => '2027-03-31', 'status' => 'open',
        ]);
        $this->grade = GradeLevel::query()->create([
            'code' => 'T-G1', 'name' => 'Fictional Grade', 'sequence' => 1,
            'school_stage' => 'Elementary', 'is_active' => true,
        ]);
    }

    public function test_walk_in_submission_fails_closed_when_requirement_rules_are_missing(): void
    {
        $enrollment = $this->enrollment('regular', 'draft', true);
        // A fully completed application form (D-049), so only the missing requirement rules can stop it.
        $enrollment->student->forceFill(['birth_place' => 'Lingayen', 'mother_tongue' => 'Pangasinan', 'current_address' => 'Libsong West, Lingayen', 'permanent_address' => 'Libsong West, Lingayen', 'is_4ps_recipient' => false, 'is_learner_with_disability' => false])->save();
        $enrollment->forceFill(['guardian_relationship' => 'mother'])->save();

        try {
            app(TransitionEnrollmentAction::class)->execute($enrollment, 'submitted', $enrollment->accessOwner);
            $this->fail('Submission must fail when no requirement rules match the application.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('application', $exception->errors());
            $this->assertStringContainsString('not configured', $exception->errors()['application'][0]);
        }

        $this->assertSame('draft', $enrollment->fresh()->status);
        $this->assertNull($enrollment->fresh()->submitted_at);
        $this->assertDatabaseCount('enrollment_requirements', 0);
    }

    public function test_controlled_transitions_block_unresolved_requirements_and_record_rejection(): void
    {
        $registrar = $this->user(['registrar']);
        $enrollment = $this->enrollment('regular', 'submitted');
        $type = DocumentType::query()->create(['code' => 'CARD-T', 'name' => 'Report card']);
        EnrollmentRequirement::query()->create([
            'enrollment_id' => $enrollment->id,
            'document_type_id' => $type->id,
            'rule_snapshot' => ['is_required' => true, 'is_blocking' => true],
            'status' => 'missing',
        ]);

        app(TransitionEnrollmentAction::class)->execute($enrollment, 'under_review', $registrar);
        $this->expectValidation(fn () => app(TransitionEnrollmentAction::class)
            ->execute($enrollment->fresh(), 'approved', $registrar));
        app(TransitionEnrollmentAction::class)->execute($enrollment->fresh(), 'incomplete', $registrar, 'Replacement required');
        app(TransitionEnrollmentAction::class)->execute($enrollment->fresh(), 'rejected', $registrar, 'Application closed after review');

        $this->assertSame('rejected', $enrollment->fresh()->status);
        $this->assertNull($enrollment->fresh()->active_enrollment_key);
        $this->assertDatabaseHas('enrollment_decisions', ['enrollment_id' => $enrollment->id, 'outcome' => 'rejected']);
    }

    public function test_approval_uses_requirements_and_support_review_gates_not_payment(): void
    {
        $registrar = $this->user(['registrar']);
        $type = DocumentType::query()->create(['code' => 'APPROVAL-T', 'name' => 'Approval document']);

        $regular = $this->enrollment('regular', 'under_review');
        $this->verifiedRequirement($regular, $type);
        app(TransitionEnrollmentAction::class)->execute($regular, 'approved', $registrar);
        $this->assertSame('approved', $regular->fresh()->status);

        $support = $this->enrollment('support_review', 'assessment_pending');
        $this->verifiedRequirement($support, $type);
        app(TransitionEnrollmentAction::class)->execute($support, 'under_review', $registrar);
        $this->expectValidation(fn () => app(TransitionEnrollmentAction::class)
            ->execute($support->fresh(), 'approved', $registrar));
        SupportCase::query()->create([
            'enrollment_id' => $support->id, 'status' => 'reviewed', 'opened_by' => $registrar->id,
            'opened_at' => now(), 'assigned_reviewer_id' => $registrar->id, 'final_outcome' => 'approved',
        ]);
        app(TransitionEnrollmentAction::class)->execute($support->fresh(), 'approved', $registrar);
        $this->assertSame('approved', $support->fresh()->status);
    }

    public function test_public_online_start_and_contact_verification_routes_are_retired(): void
    {
        $this->assertFalse(Route::has('enrollment.public.store'));
        $this->assertFalse(Route::has('enrollment.public.verify'));
        $this->assertFalse(Route::has('enrollment.public.resend'));
        $this->assertFalse(Route::has('enrollment.registrar.connect'));
        $this->get('/apply')->assertNotFound();
        $this->post('/apply')->assertNotFound();
    }

    public function test_phase_thirteen_columns_and_foreign_keys_protect_history(): void
    {
        $this->assertTrue(Schema::hasColumns('users', ['temporary_access_expires_at']));
        $this->assertTrue(Schema::hasColumns('enrollments', [
            'access_issued_to_user_id', 'walk_in_verified_by', 'walk_in_verified_at', 'access_issued_at',
        ]));

        $admin = $this->user(['admin']);
        $enrollment = $this->enrollment('support_review', 'assessment_required');
        $case = app(CreateSupportCaseAction::class)->execute($enrollment, $admin);
        try {
            $enrollment->student->delete();
            $this->fail('A student with enrollment history must not be deleted.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
        try {
            $case->delete();
            $this->fail('A support case with event history must not be deleted.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
    }

    public function test_database_rejects_a_second_active_application_for_the_same_student_and_year(): void
    {
        $enrollment = $this->enrollment('regular', 'draft');
        $this->expectException(QueryException::class);
        Enrollment::query()->create($this->enrollmentAttributes($enrollment->student_id, 'DUP-'.Str::random(8)));
    }

    private function verifiedRequirement(Enrollment $enrollment, DocumentType $type): void
    {
        EnrollmentRequirement::query()->create([
            'enrollment_id' => $enrollment->id,
            'document_type_id' => $type->id,
            'rule_snapshot' => ['is_required' => true, 'is_blocking' => true],
            'status' => 'verified',
        ]);
    }

    private function expectValidation(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected validation to fail.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    private function enrollment(string $category, string $status, bool $withOwner = false): Enrollment
    {
        $guardian = GuardianProfile::query()->create([
            'first_name' => 'Fictional', 'last_name' => 'Guardian',
            'email' => Str::lower(Str::random(8)).'@example.test',
        ]);
        $student = StudentProfile::query()->create([
            'first_name' => 'Fictional', 'last_name' => Str::random(6), 'birth_date' => '2015-01-02',
        ]);
        $owner = $withOwner ? $this->user(['guardian']) : null;

        return Enrollment::query()->create(array_merge(
            $this->enrollmentAttributes($student->id, 'APP-'.Str::random(8)),
            [
                'applicant_guardian_id' => $guardian->id,
                'student_category' => $category,
                'status' => $status,
                'access_issued_to_user_id' => $owner?->id,
                'walk_in_verified_at' => $withOwner ? now() : null,
            ],
        ));
    }

    /** @return array<string, mixed> */
    private function enrollmentAttributes(int $studentId, string $application): array
    {
        return [
            'application_number' => $application,
            'student_id' => $studentId,
            'academic_year_id' => $this->year->id,
            'grade_level_id' => $this->grade->id,
            'student_category' => 'regular',
            'status' => 'draft',
            'active_enrollment_key' => $studentId.':'.$this->year->id,
        ];
    }

    /** @param list<string> $roles */
    private function user(array $roles): User
    {
        $user = User::query()->create([
            'name' => 'Fictional '.Str::random(5),
            'email' => Str::lower(Str::random(8)).'@example.test',
            'password' => Hash::make('Password12345'),
            'status' => AccountStatus::Active,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        return app(AssignRolesAction::class)->execute($user, $roles, null);
    }
}
