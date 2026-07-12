<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Enrollment\Actions\StartEnrollmentAction;
use App\Modules\Enrollment\Actions\TransitionEnrollmentAction;
use App\Modules\Enrollment\Mail\EnrollmentContactVerificationMail;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Requirements\Models\DocumentType;
use App\Modules\Requirements\Models\RequirementRule;
use App\Modules\StudentSupport\Actions\CreateSupportCaseAction;
use App\Modules\StudentSupport\Models\SupportCase;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
        $this->year = AcademicYear::query()->create(['code' => '2026-T', 'name' => 'Fictional 2026', 'starts_on' => '2026-06-01', 'ends_on' => '2027-03-31', 'status' => 'open']);
        $this->grade = GradeLevel::query()->create(['code' => 'T-G1', 'name' => 'Fictional Grade', 'sequence' => 1, 'is_active' => true]);
    }

    public function test_regular_public_draft_is_private_verified_resumable_and_submittable(): void
    {
        Mail::fake();
        $type = DocumentType::query()->create(['code' => 'BIRTH-T', 'name' => 'Birth certificate']);
        RequirementRule::query()->create(['academic_year_id' => $this->year->id, 'student_category' => 'regular', 'document_type_id' => $type->id, 'is_required' => true, 'is_blocking' => true]);

        $response = $this->post(route('enrollment.public.store'), $this->startPayload());
        $response->assertRedirect()->assertSessionHas('verification_code');
        Mail::assertQueued(EnrollmentContactVerificationMail::class);
        $enrollment = Enrollment::query()->firstOrFail();
        $token = $this->tokenFromLocation($response->headers->get('Location'));
        $code = (string) session('verification_code');

        $this->get(route('enrollment.public.status', $enrollment))->assertNotFound();
        $this->get(route('enrollment.public.status', ['enrollment' => $enrollment, 'token' => Str::random(64)]))->assertNotFound();
        $this->get(route('enrollment.public.status', ['enrollment' => $enrollment, 'token' => $token]))->assertOk()->assertSee('Verify the contact');
        $this->post(route('enrollment.public.verify', $enrollment), ['resume_token' => $token, 'verification_code' => '000000'])->assertSessionHasErrors('verification_code');
        $this->post(route('enrollment.public.verify', $enrollment), ['resume_token' => $token, 'verification_code' => $code])->assertRedirect();
        $this->assertNotNull($enrollment->fresh()->contact_verified_at);

        $this->put(route('enrollment.public.update', $enrollment), ['resume_token' => Str::random(64), 'guardian_relationship' => 'Parent'])->assertNotFound();
        $this->put(route('enrollment.public.update', $enrollment), ['resume_token' => $token, 'guardian_relationship' => 'Parent', 'student_middle_name' => 'Fictional'])->assertRedirect();
        $this->post(route('enrollment.public.submit', $enrollment), ['resume_token' => $token])->assertRedirect();

        $this->assertSame('submitted', $enrollment->fresh()->status);
        $this->assertDatabaseHas('enrollment_requirements', ['enrollment_id' => $enrollment->id, 'document_type_id' => $type->id, 'status' => 'missing']);
        $this->assertDatabaseHas('enrollment_status_history', ['enrollment_id' => $enrollment->id, 'from_status' => 'draft', 'to_status' => 'submitted']);
    }

    public function test_transferee_required_fields_block_submission_until_completed(): void
    {
        Mail::fake();
        $type = DocumentType::query()->create(['code' => 'FORM137-T', 'name' => 'Form 137']);
        RequirementRule::query()->create(['academic_year_id' => $this->year->id, 'student_category' => 'transferee', 'document_type_id' => $type->id, 'is_required' => true, 'is_blocking' => true]);
        $payload = $this->startPayload();
        $payload['student_category'] = 'transferee';
        $started = app(StartEnrollmentAction::class)->execute($payload);
        $enrollment = $started->enrollment;
        $enrollment->update(['contact_verified_at' => now()]);

        $this->put(route('enrollment.public.update', $enrollment), ['resume_token' => $started->resumeToken, 'guardian_relationship' => 'Parent'])->assertSessionHasErrors('previous_school_name');
        $this->post(route('enrollment.public.submit', $enrollment), ['resume_token' => $started->resumeToken])->assertSessionHasErrors('application');
        $this->put(route('enrollment.public.update', $enrollment), ['resume_token' => $started->resumeToken, 'guardian_relationship' => 'Parent', 'previous_school_name' => 'Fictional Previous School'])->assertRedirect();
        $this->post(route('enrollment.public.submit', $enrollment), ['resume_token' => $started->resumeToken])->assertRedirect();

        $this->assertSame('submitted', $enrollment->fresh()->status);
        $this->assertSame('Fictional Previous School', $enrollment->fresh()->previous_school_name);
        $this->assertDatabaseHas('enrollment_requirements', ['enrollment_id' => $enrollment->id, 'document_type_id' => $type->id]);
    }

    public function test_controlled_transitions_block_unresolved_requirements_and_record_rejection_decision(): void
    {
        $registrar = $this->user(['registrar']);
        $enrollment = $this->enrollment('regular', 'submitted');
        $type = DocumentType::query()->create(['code' => 'CARD-T', 'name' => 'Report card']);
        EnrollmentRequirement::query()->create(['enrollment_id' => $enrollment->id, 'document_type_id' => $type->id, 'rule_snapshot' => ['is_required' => true, 'is_blocking' => true], 'status' => 'missing']);

        app(TransitionEnrollmentAction::class)->execute($enrollment, 'under_review', $registrar);
        try {
            app(TransitionEnrollmentAction::class)->execute($enrollment->fresh(), 'approved', $registrar);
            $this->fail('Approval must be blocked while a blocking requirement is unresolved.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        app(TransitionEnrollmentAction::class)->execute($enrollment->fresh(), 'incomplete', $registrar, 'Replacement required');
        app(TransitionEnrollmentAction::class)->execute($enrollment->fresh(), 'rejected', $registrar, 'Application closed after review');

        $this->assertSame('rejected', $enrollment->fresh()->status);
        $this->assertNull($enrollment->fresh()->active_enrollment_key);
        $this->assertDatabaseHas('enrollment_decisions', ['enrollment_id' => $enrollment->id, 'outcome' => 'rejected']);
    }

    public function test_duplicate_candidates_require_review_and_active_identity_is_prevented(): void
    {
        $registrar = $this->user(['registrar']);
        $candidate = StudentProfile::query()->create(['first_name' => 'Fictional', 'last_name' => 'Learner', 'birth_date' => '2015-01-02']);
        $payload = $this->startPayload();
        $guardianCandidate = GuardianProfile::query()->create(['first_name' => 'Existing', 'last_name' => 'Guardian', 'email' => $payload['guardian_email']]);
        $response = $this->post(route('enrollment.public.store'), $payload);
        $response->assertRedirect();
        $enrollment = Enrollment::query()->where('student_id', '!=', $candidate->id)->firstOrFail();
        $this->assertSame('pending', $enrollment->duplicate_review_status);
        $this->assertSame($guardianCandidate->id, $enrollment->potential_duplicate_guardian_id);
        $this->post(route('enrollment.public.store'), $payload)->assertSessionHasErrors('student_first_name');
        $enrollment->update(['contact_verified_at' => now(), 'status' => 'submitted']);

        $this->actingAs($registrar)->post(route('enrollment.registrar.transition', $enrollment), ['status' => 'under_review'])->assertSessionHasErrors('status');
        $this->actingAs($registrar)->post(route('enrollment.registrar.duplicate-review', $enrollment), ['duplicate_decision' => 'clear', 'reason' => 'Distinct identity confirmed in person'])->assertRedirect();
        $this->actingAs($registrar)->post(route('enrollment.registrar.transition', $enrollment), ['status' => 'under_review'])->assertRedirect();

        $this->expectException(QueryException::class);
        Enrollment::query()->create($this->enrollmentAttributes($enrollment->student_id, 'DUP-'.Str::random(8)));
    }

    public function test_regular_and_support_review_approval_require_domain_gates_not_payment(): void
    {
        $registrar = $this->user(['registrar']);
        $type = DocumentType::query()->create(['code' => 'APPROVAL-T', 'name' => 'Approval document']);

        $regular = $this->enrollment('regular', 'under_review');
        EnrollmentRequirement::query()->create(['enrollment_id' => $regular->id, 'document_type_id' => $type->id, 'rule_snapshot' => ['is_required' => true, 'is_blocking' => true], 'status' => 'verified']);
        app(TransitionEnrollmentAction::class)->execute($regular, 'approved', $registrar);
        $this->assertSame('approved', $regular->fresh()->status);

        $support = $this->enrollment('support_review', 'assessment_pending');
        EnrollmentRequirement::query()->create(['enrollment_id' => $support->id, 'document_type_id' => $type->id, 'rule_snapshot' => ['is_required' => true, 'is_blocking' => true], 'status' => 'verified']);
        app(TransitionEnrollmentAction::class)->execute($support, 'under_review', $registrar);
        try {
            app(TransitionEnrollmentAction::class)->execute($support->fresh(), 'approved', $registrar);
            $this->fail('Support-review enrollment must not approve without an approved case.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        SupportCase::query()->create(['enrollment_id' => $support->id, 'status' => 'reviewed', 'opened_by' => $registrar->id, 'opened_at' => now(), 'assigned_reviewer_id' => $registrar->id, 'final_outcome' => 'approved']);
        app(TransitionEnrollmentAction::class)->execute($support->fresh(), 'approved', $registrar);
        $this->assertSame('approved', $support->fresh()->status);
        $this->assertDatabaseHas('enrollment_decisions', ['enrollment_id' => $support->id, 'outcome' => 'approved']);
    }

    public function test_guardian_invitation_connection_and_role_authorization_boundaries(): void
    {
        $registrar = $this->user(['registrar']);
        $admin = $this->user(['admin']);
        $accounting = $this->user(['accounting']);
        $teacher = $this->user(['teacher']);
        $enrollment = $this->enrollment('regular', 'submitted');
        $enrollment->update(['contact_verified_at' => now(), 'guardian_relationship' => 'Parent', 'duplicate_review_status' => 'clear']);

        $this->get(route('enrollment.registrar.index'))->assertRedirect(route('identity.login'));
        $this->actingAs($accounting)->get(route('enrollment.registrar.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('enrollment.registrar.index'))->assertOk();
        $this->actingAs($teacher)->post(route('enrollment.registrar.transition', $enrollment), ['status' => 'under_review'])->assertForbidden();
        $this->actingAs($registrar)->post(route('enrollment.registrar.connect', $enrollment))->assertRedirect()->assertSessionHas('invitation_url');

        $guardian = $enrollment->guardian->fresh();
        $this->assertNotNull($guardian->user_id);
        $this->assertTrue($guardian->user->hasRole('guardian'));
        $this->assertDatabaseHas('guardian_student', ['guardian_id' => $guardian->id, 'student_id' => $enrollment->student_id, 'may_manage_enrollment' => true]);
        $this->assertDatabaseHas('account_invitations', ['user_id' => $guardian->user_id, 'purpose' => 'account_setup']);
        $this->actingAs($guardian->user)->get(route('guardian.enrollment.progress'))->assertOk()->assertSee($enrollment->application_number)->assertDontSee('duplicate_review_status');
    }

    public function test_public_start_is_rate_limited_and_phase_four_indexes_exist(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $payload = $this->startPayload();
            $payload['student_first_name'] = 'Fictional'.$attempt;
            $this->post(route('enrollment.public.store'), $payload)->assertRedirect();
        }
        $payload = $this->startPayload();
        $payload['student_first_name'] = 'Limited';
        $this->post(route('enrollment.public.store'), $payload)->assertTooManyRequests();

        $enrollmentIndexes = collect(Schema::getIndexes('enrollments'))->pluck('name');
        $supportIndexes = collect(Schema::getIndexes('support_cases'))->pluck('name');
        $this->assertTrue($enrollmentIndexes->contains(fn ($name) => str_contains($name, 'active_enrollment_key')));
        $this->assertContains('support_reviewer_status_index', $supportIndexes);
        foreach (['enrollments', 'enrollment_status_history', 'enrollment_requirements', 'document_uploads', 'support_cases', 'support_case_events'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
    }

    public function test_phase_four_foreign_keys_protect_enrollment_and_support_history(): void
    {
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

        $this->assertDatabaseHas('support_case_events', ['support_case_id' => $case->id, 'event_type' => 'case_opened']);
    }

    public function test_mysql_identity_lock_serializes_duplicate_public_starts(): void
    {
        $payload = $this->startPayload();
        $lockName = 'jca:enroll:'.substr(hash('sha256', implode('|', [
            Str::lower(trim($payload['student_first_name'])),
            Str::lower(trim($payload['student_last_name'])),
            $payload['birth_date'],
            $payload['academic_year_id'],
        ])), 0, 40);
        config(['database.connections.enrollment_lock_holder' => config('database.connections.mysql')]);
        $holder = DB::connection('enrollment_lock_holder');
        $this->assertSame(1, (int) $holder->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName])->acquired);

        try {
            app(StartEnrollmentAction::class)->execute($payload);
            $this->fail('A concurrent start with the same identity key must wait or fail safely.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('student_first_name', $exception->errors());
        } finally {
            $holder->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            DB::disconnect('enrollment_lock_holder');
        }
    }

    /** @return array<string, mixed> */
    private function startPayload(): array
    {
        return ['guardian_first_name' => 'Fictional', 'guardian_last_name' => 'Guardian', 'guardian_email' => Str::lower(Str::random(8)).'@example.test', 'student_first_name' => 'Fictional', 'student_last_name' => 'Learner', 'birth_date' => '2015-01-02', 'academic_year_id' => $this->year->id, 'grade_level_id' => $this->grade->id, 'student_category' => 'regular'];
    }

    private function tokenFromLocation(?string $location): string
    {
        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

        return (string) $query['token'];
    }

    private function enrollment(string $category, string $status): Enrollment
    {
        $guardian = GuardianProfile::query()->create(['first_name' => 'Fictional', 'last_name' => 'Guardian', 'email' => Str::lower(Str::random(8)).'@example.test']);
        $student = StudentProfile::query()->create(['first_name' => 'Fictional', 'last_name' => Str::random(6), 'birth_date' => '2015-01-02']);

        return Enrollment::query()->create(array_merge($this->enrollmentAttributes($student->id, 'APP-'.Str::random(8)), ['applicant_guardian_id' => $guardian->id, 'student_category' => $category, 'status' => $status]));
    }

    /** @return array<string, mixed> */
    private function enrollmentAttributes(int $studentId, string $application): array
    {
        return ['application_number' => $application, 'student_id' => $studentId, 'academic_year_id' => $this->year->id, 'grade_level_id' => $this->grade->id, 'student_category' => 'regular', 'status' => 'draft', 'active_enrollment_key' => $studentId.':'.$this->year->id];
    }

    /** @param list<string> $roles */
    private function user(array $roles): User
    {
        $user = User::query()->create(['name' => 'Fictional '.Str::random(5), 'email' => Str::lower(Str::random(8)).'@example.test', 'password' => Hash::make('Password12345'), 'status' => AccountStatus::Active, 'password_changed_at' => now()]);

        return app(AssignRolesAction::class)->execute($user, $roles, null);
    }
}
