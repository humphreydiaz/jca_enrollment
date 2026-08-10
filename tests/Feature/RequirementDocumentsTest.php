<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Enrollment\Actions\TransitionEnrollmentAction;
use App\Modules\Enrollment\Models\DocumentUpload;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Requirements\Models\DocumentType;
use App\Modules\Requirements\Models\RequirementRule;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RequirementDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('documents');
    }

    public function test_submission_snapshots_rules_and_registrar_records_and_verifies_hard_copy(): void
    {
        $admin = $this->user(['admin']);
        $registrar = $this->user(['registrar']);
        [$enrollment, $guardian] = $this->enrollment();
        $type = DocumentType::query()->create(['code' => 'BIRTH', 'name' => 'Fictional birth certificate']);
        RequirementRule::query()->create([
            'academic_year_id' => $enrollment->academic_year_id,
            'student_category' => 'regular',
            'document_type_id' => $type->id,
            'is_required' => true,
            'is_blocking' => true,
        ]);
        $enrollment->update(['contact_verified_at' => now()]);

        app(TransitionEnrollmentAction::class)->execute($enrollment, 'submitted', $admin);

        $requirement = EnrollmentRequirement::query()->firstOrFail();
        $this->assertSame('missing', $requirement->status);
        $this->assertNull($requirement->received_at);

        $this->actingAs($registrar)->put(route('requirements.hard-copy.update', $requirement), [
            'received' => true,
            'receipt_notes' => 'Original inspected at the Registrar counter.',
        ])->assertRedirect();

        $requirement->refresh();
        $this->assertSame('submitted', $requirement->status);
        $this->assertSame($registrar->id, $requirement->received_by);
        $this->assertNotNull($requirement->received_at);
        $this->assertSame('Original inspected at the Registrar counter.', $requirement->receipt_notes);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'requirements.hard_copy.received',
            'actor_user_id' => $registrar->id,
        ]);

        $this->actingAs($registrar)->post(route('requirements.review', $requirement), [
            'status' => 'verified',
        ])->assertRedirect();

        $this->assertSame('verified', $requirement->fresh()->status);
        $this->assertDatabaseCount('document_uploads', 0);

        app(TransitionEnrollmentAction::class)->execute($enrollment->fresh(), 'under_review', $registrar);
        $this->actingAs($registrar)
            ->get(route('enrollment.registrar.index', ['status' => 'under_review']))
            ->assertOk()
            ->assertSee('Enrollment requirement evidence')
            ->assertDontSee('<option value="submitted">', false);
        $this->actingAs($guardian)
            ->get(route('guardian.enrollment.progress'))
            ->assertOk()
            ->assertSee('Hard copies remain required')
            ->assertSee('type="file"', false);
    }

    public function test_hard_copy_receipt_is_staff_only_and_can_be_corrected_before_review(): void
    {
        $registrar = $this->user(['registrar']);
        $otherGuardian = $this->user(['guardian']);
        $teacher = $this->user(['teacher']);
        $accounting = $this->user(['accounting']);
        [$enrollment, $guardian] = $this->enrollment();
        $requirement = $this->requirement($enrollment);

        foreach ([$guardian, $otherGuardian, $teacher, $accounting] as $unauthorized) {
            $this->actingAs($unauthorized)->put(route('requirements.hard-copy.update', $requirement), [
                'received' => true,
            ])->assertForbidden();
        }

        $this->actingAs($registrar)->put(route('requirements.hard-copy.update', $requirement), [
            'received' => true,
        ])->assertRedirect();
        $this->assertSame('submitted', $requirement->fresh()->status);

        $this->actingAs($registrar)->put(route('requirements.hard-copy.update', $requirement), [
            'received' => false,
        ])->assertRedirect();

        $requirement->refresh();
        $this->assertSame('missing', $requirement->status);
        $this->assertNull($requirement->received_by);
        $this->assertNull($requirement->received_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'requirements.hard_copy.marked_missing',
            'actor_user_id' => $registrar->id,
        ]);
    }

    public function test_rejected_requirement_requires_a_new_hard_copy_before_review(): void
    {
        $registrar = $this->user(['registrar']);
        [$enrollment] = $this->enrollment();
        $requirement = $this->requirement($enrollment);

        $this->actingAs($registrar)->put(route('requirements.hard-copy.update', $requirement), [
            'received' => true,
        ])->assertRedirect();
        $firstReceipt = $requirement->fresh()->received_at;

        $this->actingAs($registrar)->post(route('requirements.review', $requirement), [
            'status' => 'rejected',
            'reason' => 'The copy is unreadable.',
        ])->assertRedirect();
        $this->assertSame('rejected', $requirement->fresh()->status);

        $this->actingAs($registrar)->post(route('requirements.review', $requirement), [
            'status' => 'verified',
        ])->assertSessionHasErrors('status');

        $this->travel(1)->minute();
        $this->actingAs($registrar)->put(route('requirements.hard-copy.update', $requirement), [
            'received' => true,
            'receipt_notes' => 'Readable replacement received.',
        ])->assertRedirect();

        $requirement->refresh();
        $this->assertSame('submitted', $requirement->status);
        $this->assertTrue($requirement->received_at->greaterThan($firstReceipt));
        $this->assertNull($requirement->reviewed_at);

        $this->actingAs($registrar)->post(route('requirements.review', $requirement), [
            'status' => 'verified',
        ])->assertRedirect();
        $this->assertSame('verified', $requirement->fresh()->status);
    }

    public function test_only_admin_can_waive_a_missing_hard_copy(): void
    {
        $registrar = $this->user(['registrar']);
        $admin = $this->user(['admin']);
        [$enrollment] = $this->enrollment();
        $requirement = $this->requirement($enrollment);

        $this->actingAs($registrar)->post(route('requirements.review', $requirement), [
            'status' => 'waived',
            'reason' => 'Not authorized.',
        ])->assertForbidden();

        $this->actingAs($admin)->post(route('requirements.review', $requirement), [
            'status' => 'waived',
            'reason' => 'Approved documented exception.',
        ])->assertRedirect();

        $this->assertSame('waived', $requirement->fresh()->status);
        $this->assertSame($admin->id, $requirement->fresh()->waived_by);
    }

    public function test_soft_copy_review_redirects_to_the_review_list_not_the_previewed_document(): void
    {
        $registrar = $this->user(['registrar']);
        [$enrollment, $guardian] = $this->enrollment();
        $requirement = $this->requirement($enrollment);
        $enrollment->update(['status' => 'under_review', 'contact_verified_at' => now(), 'duplicate_review_status' => 'clear']);
        $this->actingAs($guardian)->post(route('requirements.documents.store', $requirement), [
            'document' => UploadedFile::fake()->create('birth-certificate.pdf', 120, 'application/pdf'),
        ])->assertRedirect();
        $document = $requirement->activeUpload()->firstOrFail();

        // Coming "from" the previewed document (the iframe pollutes the previous URL)
        // must not strand the reviewer on the raw file, and must keep the toast.
        $this->actingAs($registrar)
            ->from(route('requirements.documents.preview', $document))
            ->post(route('requirements.soft-copy.review', $requirement), ['soft_copy_status' => 'verified'])
            ->assertRedirect(route('enrollment.registrar.index'))
            ->assertSessionHas('status', 'Soft-copy review recorded.');

        // The normal case still returns to wherever the reviewer was (with filters).
        $listUrl = route('enrollment.registrar.index', ['status' => 'under_review']);
        $this->actingAs($registrar)
            ->from($listUrl)
            ->post(route('requirements.soft-copy.review', $requirement), ['soft_copy_status' => 'verified'])
            ->assertRedirect($listUrl);
    }

    public function test_verified_soft_copy_allows_approval_while_hard_copy_remains_outstanding(): void
    {
        $registrar = $this->user(['registrar']);
        [$enrollment, $guardian] = $this->enrollment();
        $requirement = $this->requirement($enrollment);
        $enrollment->update(['status' => 'under_review', 'contact_verified_at' => now(), 'duplicate_review_status' => 'clear']);

        $this->actingAs($guardian)->post(route('requirements.documents.store', $requirement), [
            'document' => UploadedFile::fake()->create('birth-certificate.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $requirement->refresh();
        $document = $requirement->activeUpload()->firstOrFail();
        $this->assertSame('submitted', $requirement->soft_copy_status);
        $this->assertSame('missing', $requirement->status);
        Storage::disk('documents')->assertExists($document->path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'requirements.soft_copy.uploaded', 'actor_user_id' => $guardian->id]);

        $this->actingAs($registrar)->get(route('requirements.documents.preview', $document))->assertOk()->assertHeader('cache-control', 'no-store, private');
        $this->assertDatabaseHas('document_access_logs', ['document_upload_id' => $document->id, 'actor_user_id' => $registrar->id, 'action' => 'preview']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'requirements.document.previewed', 'actor_user_id' => $registrar->id]);

        $this->actingAs($registrar)->post(route('requirements.soft-copy.review', $requirement), [
            'soft_copy_status' => 'verified',
        ])->assertRedirect();

        $requirement->refresh();
        $this->assertSame('verified', $requirement->soft_copy_status);
        $this->assertSame('missing', $requirement->status);
        $this->assertNull($requirement->received_at);

        app(TransitionEnrollmentAction::class)->execute($enrollment->fresh(), 'approved', $registrar);
        $this->assertSame('approved', $enrollment->fresh()->status);
        $this->assertSame('missing', $requirement->fresh()->status);

        $this->actingAs($guardian)
            ->get(route('guardian.enrollment.progress'))
            ->assertOk()
            ->assertSee('Admission evidence complete')
            ->assertSee('Still required');
    }

    public function test_recorded_hard_copy_receipt_allows_approval_without_a_soft_copy(): void
    {
        $registrar = $this->user(['registrar']);
        [$enrollment] = $this->enrollment();
        $requirement = $this->requirement($enrollment);
        $enrollment->update(['status' => 'under_review', 'contact_verified_at' => now(), 'duplicate_review_status' => 'clear']);

        $this->actingAs($registrar)->put(route('requirements.hard-copy.update', $requirement), [
            'received' => true,
            'receipt_notes' => 'Physical original received.',
        ])->assertRedirect();

        $this->assertSame('submitted', $requirement->fresh()->status);
        $this->assertSame('not_uploaded', $requirement->fresh()->soft_copy_status);
        app(TransitionEnrollmentAction::class)->execute($enrollment->fresh(), 'approved', $registrar);
        $this->assertSame('approved', $enrollment->fresh()->status);
    }

    public function test_soft_copy_submission_alone_does_not_allow_approval_and_rejection_requires_a_reason(): void
    {
        $registrar = $this->user(['registrar']);
        [$enrollment, $guardian] = $this->enrollment();
        $requirement = $this->requirement($enrollment);
        $enrollment->update(['status' => 'under_review', 'contact_verified_at' => now(), 'duplicate_review_status' => 'clear']);

        $this->actingAs($guardian)->post(route('requirements.documents.store', $requirement), [
            'document' => UploadedFile::fake()->image('report-card.jpg'),
        ])->assertRedirect();

        try {
            app(TransitionEnrollmentAction::class)->execute($enrollment->fresh(), 'approved', $registrar);
            $this->fail('An unreviewed soft copy must not satisfy admission.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->actingAs($registrar)->post(route('requirements.soft-copy.review', $requirement), [
            'soft_copy_status' => 'rejected',
        ])->assertSessionHasErrors('reason');
        $this->assertSame('submitted', $requirement->fresh()->soft_copy_status);
    }

    public function test_guardian_or_student_owner_can_replace_a_rejected_soft_copy_but_other_roles_and_families_cannot_upload(): void
    {
        $registrar = $this->user(['registrar']);
        $otherGuardianUser = $this->user(['guardian']);
        $teacher = $this->user(['teacher']);
        $accounting = $this->user(['accounting']);
        [$enrollment, $guardian] = $this->enrollment();
        GuardianProfile::query()->create(['user_id' => $otherGuardianUser->id, 'first_name' => 'Other', 'last_name' => 'Guardian']);
        $requirement = $this->requirement($enrollment);
        $enrollment->update(['status' => 'under_review']);

        auth()->logout();
        $this->post(route('requirements.documents.store', $requirement), [
            'document' => UploadedFile::fake()->create('birth.pdf', 20, 'application/pdf'),
        ])->assertRedirect(route('identity.login'));

        foreach ([$otherGuardianUser, $teacher, $accounting, $registrar] as $unauthorized) {
            $this->actingAs($unauthorized)->post(route('requirements.documents.store', $requirement), [
                'document' => UploadedFile::fake()->create('birth.pdf', 20, 'application/pdf'),
            ])->assertNotFound();
        }

        $this->actingAs($guardian)->post(route('requirements.documents.store', $requirement), [
            'document' => UploadedFile::fake()->create('first.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $first = $requirement->fresh()->activeUpload()->firstOrFail();

        $this->actingAs($registrar)->post(route('requirements.soft-copy.review', $requirement), [
            'soft_copy_status' => 'rejected',
            'reason' => 'The first scan is unreadable.',
        ])->assertRedirect();

        $this->actingAs($guardian)->post(route('requirements.documents.store', $requirement), [
            'document' => UploadedFile::fake()->create('replacement.pdf', 25, 'application/pdf'),
        ])->assertRedirect();

        $replacement = $requirement->fresh()->activeUpload()->firstOrFail();
        $this->assertSame(1, $first->version);
        $this->assertNotNull($first->fresh()->archived_at);
        $this->assertSame(2, $replacement->version);
        $this->assertSame('submitted', $requirement->fresh()->soft_copy_status);
        $this->assertNull($requirement->fresh()->soft_copy_review_reason);
        $this->actingAs($registrar)->get(route('requirements.documents.preview', $first))->assertNotFound();
        $this->actingAs($registrar)->get(route('requirements.documents.preview', $replacement))->assertOk();
    }

    public function test_student_applicant_can_upload_valid_files_and_unsafe_files_are_rejected(): void
    {
        $studentUser = $this->user(['student']);
        [$enrollment] = $this->enrollment();
        $requirement = $this->requirement($enrollment);
        $enrollment->update(['status' => 'submitted', 'student_applicant_user_id' => $studentUser->id]);

        $this->actingAs($studentUser)->post(route('requirements.documents.store', $requirement), [
            'document' => UploadedFile::fake()->create('payload.exe', 20, 'application/x-msdownload'),
        ])->assertSessionHasErrors('document');
        $this->assertDatabaseCount('document_uploads', 0);

        $this->actingAs($studentUser)->post(route('requirements.documents.store', $requirement), [
            'document' => UploadedFile::fake()->create('too-large.pdf', 5121, 'application/pdf'),
        ])->assertSessionHasErrors('document');
        $this->assertDatabaseCount('document_uploads', 0);

        $this->actingAs($studentUser)->post(route('requirements.documents.store', $requirement), [
            'document' => UploadedFile::fake()->create('form-137.pdf', 200, 'application/pdf'),
        ])->assertRedirect();
        $this->assertDatabaseCount('document_uploads', 1);

        $this->actingAs($studentUser)
            ->get(route('student.enrollment.progress'))
            ->assertOk()
            ->assertSee('Soft copy submitted successfully')
            ->assertSee('Replace soft copy');
    }

    public function test_historical_private_downloads_remain_protected_and_logged(): void
    {
        [$enrollment, $guardian] = $this->enrollment();
        $other = $this->user(['guardian']);
        $teacher = $this->user(['teacher']);
        $accounting = $this->user(['accounting']);
        $student = $this->user(['student']);
        GuardianProfile::query()->create(['user_id' => $other->id, 'first_name' => 'Other', 'last_name' => 'Family']);
        $requirement = $this->requirement($enrollment);
        $path = 'enrollments/historical-example.pdf';
        Storage::disk('documents')->put($path, 'historical test document');
        $document = DocumentUpload::query()->create([
            'enrollment_requirement_id' => $requirement->id,
            'disk' => 'documents',
            'path' => $path,
            'original_filename' => 'historical-example.pdf',
            'stored_filename' => 'historical-example.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'byte_size' => 24,
            'checksum' => hash('sha256', 'historical test document'),
            'uploaded_by' => $guardian->id,
            'version' => 1,
        ]);

        foreach ([$other, $teacher, $accounting, $student] as $unauthorized) {
            $this->actingAs($unauthorized)->get(route('requirements.documents.download', $document))->assertNotFound();
        }
        $this->assertDatabaseCount('document_access_logs', 0);

        auth()->logout();
        $this->get(route('requirements.documents.download', $document))->assertRedirect(route('identity.login'));
        $this->actingAs($guardian)->get(route('requirements.documents.download', $document))->assertOk();
        $this->assertDatabaseHas('document_access_logs', [
            'document_upload_id' => $document->id,
            'actor_user_id' => $guardian->id,
            'action' => 'download',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'requirements.document.downloaded',
            'actor_user_id' => $guardian->id,
        ]);
    }

    private function requirement(Enrollment $enrollment): EnrollmentRequirement
    {
        $type = DocumentType::query()->create([
            'code' => 'DOC-'.Str::upper(Str::random(6)),
            'name' => 'Fictional requirement',
        ]);

        return EnrollmentRequirement::query()->create([
            'enrollment_id' => $enrollment->id,
            'document_type_id' => $type->id,
            'rule_snapshot' => ['is_required' => true, 'is_blocking' => true],
        ]);
    }

    private function enrollment(): array
    {
        $year = AcademicYear::query()->create(['code' => 'TEST', 'name' => 'Test year', 'starts_on' => '2026-06-01', 'ends_on' => '2027-03-31', 'status' => 'open']);
        $grade = GradeLevel::query()->create(['code' => 'T1', 'name' => 'Test grade', 'sequence' => 1, 'is_active' => true]);
        $guardianUser = $this->user(['guardian']);
        $guardian = GuardianProfile::query()->create(['user_id' => $guardianUser->id, 'first_name' => 'Fictional', 'last_name' => 'Guardian']);
        $student = StudentProfile::query()->create(['first_name' => 'Fictional', 'last_name' => 'Student']);

        return [Enrollment::query()->create(['application_number' => 'APP-'.Str::random(8), 'student_id' => $student->id, 'academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'applicant_guardian_id' => $guardian->id, 'student_category' => 'regular', 'status' => 'draft', 'active_enrollment_key' => $student->id.':'.$year->id]), $guardianUser];
    }

    private function user(array $roles): User
    {
        $user = User::query()->create(['name' => 'Fictional '.Str::random(5), 'email' => Str::random(8).'@example.test', 'password' => Hash::make('Password12345'), 'status' => AccountStatus::Active, 'password_changed_at' => now()]);

        return app(AssignRolesAction::class)->execute($user, $roles, null);
    }
}
