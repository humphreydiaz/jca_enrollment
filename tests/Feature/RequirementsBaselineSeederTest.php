<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Requirements\Models\DocumentType;
use App\Modules\Requirements\Models\RequirementRule;
use Database\Seeders\Identity\RoleSeeder;
use Database\Seeders\Requirements\RequirementsBaselineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RequirementsBaselineSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('documents');
    }

    public function test_it_installs_missing_rules_repairs_in_flight_checklists_and_preserves_existing_policy(): void
    {
        [$enrollment, $guardian] = $this->inFlightEnrollment();
        $birthCertificate = DocumentType::query()->create([
            'code' => 'BIRTH_CERTIFICATE',
            'name' => 'PSA Birth Certificate',
            'sensitivity_class' => 'standard',
            'is_active' => true,
        ]);
        RequirementRule::query()->create([
            'academic_year_id' => $enrollment->academic_year_id,
            'student_category' => 'regular',
            'document_type_id' => $birthCertificate->id,
            'is_required' => true,
            'is_blocking' => false,
            'notes' => 'School-approved custom handling.',
        ]);

        app(RequirementsBaselineSeeder::class)->run();
        app(RequirementsBaselineSeeder::class)->run();

        $this->assertDatabaseCount('document_types', 4);
        $this->assertDatabaseCount('requirement_rules', 7);
        $this->assertDatabaseCount('enrollment_requirements', 2);
        $this->assertDatabaseHas('document_types', ['id' => $birthCertificate->id, 'name' => 'PSA Birth Certificate']);
        $this->assertDatabaseHas('requirement_rules', [
            'academic_year_id' => $enrollment->academic_year_id,
            'student_category' => 'regular',
            'document_type_id' => $birthCertificate->id,
            'is_blocking' => false,
            'notes' => 'School-approved custom handling.',
        ]);

        $requirement = EnrollmentRequirement::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('document_type_id', $birthCertificate->id)
            ->firstOrFail();
        $this->assertFalse((bool) $requirement->rule_snapshot['is_blocking']);

        $this->actingAs($guardian)
            ->get(route('guardian.enrollment.progress'))
            ->assertOk()
            ->assertSee('type="file"', false)
            ->assertSee('PSA Birth Certificate');

        $this->actingAs($guardian)->post(route('requirements.documents.store', $requirement), [
            'document' => UploadedFile::fake()->image('birth-certificate.jpg'),
        ])->assertRedirect();

        $upload = $requirement->fresh()->activeUpload()->firstOrFail();
        Storage::disk('documents')->assertExists($upload->path);
        $this->assertSame('submitted', $requirement->fresh()->soft_copy_status);
    }

    /** @return array{0: Enrollment, 1: User} */
    private function inFlightEnrollment(): array
    {
        $year = AcademicYear::query()->create([
            'code' => '2026-2027',
            'name' => 'School Year 2026-2027',
            'starts_on' => '2026-06-01',
            'ends_on' => '2027-03-31',
            'status' => 'open',
        ]);
        $grade = GradeLevel::query()->create([
            'code' => 'G10-T',
            'name' => 'Grade 10',
            'sequence' => 10,
            'school_stage' => 'Junior High School',
            'is_active' => true,
        ]);
        $guardianUser = User::query()->create([
            'name' => 'Fictional Guardian',
            'email' => 'guardian@example.test',
            'password' => Hash::make('Password12345'),
            'status' => AccountStatus::Active,
            'password_changed_at' => now(),
        ]);
        app(AssignRolesAction::class)->execute($guardianUser, ['guardian'], null);
        $guardian = GuardianProfile::query()->create([
            'user_id' => $guardianUser->id,
            'first_name' => 'Fictional',
            'last_name' => 'Guardian',
        ]);
        $student = StudentProfile::query()->create([
            'first_name' => 'Fictional',
            'last_name' => 'Student',
        ]);
        $enrollment = Enrollment::query()->create([
            'application_number' => 'JCA-2026-TESTREQ',
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'grade_level_id' => $grade->id,
            'applicant_guardian_id' => $guardian->id,
            'student_category' => 'regular',
            'status' => 'under_review',
            'active_enrollment_key' => $student->id.':'.$year->id,
            'contact_verified_at' => now(),
            'submitted_at' => now(),
            'duplicate_review_status' => 'clear',
        ]);

        return [$enrollment, $guardianUser];
    }
}
