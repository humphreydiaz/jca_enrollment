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

    public function test_submission_snapshots_category_rules_and_private_uploads_are_replaced(): void
    {
        $admin = $this->user(['admin']);
        [$enrollment,$guardian] = $this->enrollment();
        $type = DocumentType::query()->create(['code' => 'BIRTH', 'name' => 'Fictional birth certificate']);
        RequirementRule::query()->create(['academic_year_id' => $enrollment->academic_year_id, 'student_category' => 'regular', 'document_type_id' => $type->id, 'is_required' => true, 'is_blocking' => true]);
        $enrollment->update(['contact_verified_at' => now()]);
        app(TransitionEnrollmentAction::class)->execute($enrollment, 'submitted', $admin);
        $requirement = EnrollmentRequirement::query()->firstOrFail();
        $this->assertSame('missing', $requirement->status);
        $first = UploadedFile::fake()->create('fictional.pdf', 100, 'application/pdf');
        $this->actingAs($guardian)->post(route('requirements.documents.store', $requirement), ['document' => $first])->assertRedirect();
        $upload = DocumentUpload::query()->firstOrFail();
        $this->assertNotSame('fictional.pdf', $upload->stored_filename);
        $this->assertStringStartsWith('enrollments/', $upload->path);
        Storage::disk('documents')->assertExists($upload->path);
        $this->actingAs($guardian)->post(route('requirements.documents.store', $requirement), ['document' => UploadedFile::fake()->image('replacement.png')])->assertRedirect();
        $this->assertNotNull($upload->fresh()->archived_at);
        $this->assertSame(2, DocumentUpload::query()->count());
    }

    public function test_invalid_files_and_cross_family_downloads_are_denied_and_downloads_are_logged(): void
    {
        [$enrollment,$guardian] = $this->enrollment();
        $other = $this->user(['guardian']);
        $teacher = $this->user(['teacher']);
        $accounting = $this->user(['accounting']);
        GuardianProfile::query()->create(['user_id' => $other->id, 'first_name' => 'Other', 'last_name' => 'Family']);
        $type = DocumentType::query()->create(['code' => 'CARD', 'name' => 'Fictional report card']);
        $requirement = EnrollmentRequirement::query()->create(['enrollment_id' => $enrollment->id, 'document_type_id' => $type->id, 'rule_snapshot' => ['is_blocking' => true]]);
        $this->actingAs($guardian)->post(route('requirements.documents.store', $requirement), ['document' => UploadedFile::fake()->create('unsafe.exe', 10, 'application/octet-stream')])->assertSessionHasErrors('document');
        $this->actingAs($guardian)->post(route('requirements.documents.store', $requirement), ['document' => UploadedFile::fake()->create('safe.pdf', 10, 'application/pdf')]);
        $document = DocumentUpload::query()->firstOrFail();
        $this->actingAs($other)->get(route('requirements.documents.download', $document))->assertForbidden();
        $this->actingAs($teacher)->get(route('requirements.documents.download', $document))->assertForbidden();
        $this->actingAs($accounting)->get(route('requirements.documents.download', $document))->assertForbidden();
        $this->actingAs($guardian)->get(route('requirements.documents.download', $document))->assertOk();
        $this->assertDatabaseHas('document_access_logs', ['document_upload_id' => $document->id, 'actor_user_id' => $guardian->id, 'action' => 'download']);
    }

    public function test_registrar_can_verify_or_reject_but_only_admin_can_waive(): void
    {
        $registrar = $this->user(['registrar']);
        $admin = $this->user(['admin']);
        [$enrollment] = $this->enrollment();
        $type = DocumentType::query()->create(['code' => 'FORM', 'name' => 'Fictional form']);
        $requirement = EnrollmentRequirement::query()->create(['enrollment_id' => $enrollment->id, 'document_type_id' => $type->id, 'rule_snapshot' => ['is_blocking' => true], 'status' => 'submitted']);
        $this->actingAs($registrar)->post(route('requirements.review', $requirement), ['status' => 'verified'])->assertRedirect();
        $this->assertSame('verified', $requirement->fresh()->status);
        $this->actingAs($registrar)->post(route('requirements.review', $requirement), ['status' => 'waived', 'reason' => 'No'])->assertForbidden();
        $this->actingAs($admin)->post(route('requirements.review', $requirement), ['status' => 'waived', 'reason' => 'Approved exception'])->assertRedirect();
        $this->assertSame('waived', $requirement->fresh()->status);
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
