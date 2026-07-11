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
use App\Modules\StudentSupport\Models\SupportCase;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentSupportAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_support_access_matrix_and_audit(): void
    {
        $admin = $this->user(['admin']);
        $registrar = $this->user(['registrar']);
        $otherRegistrar = $this->user(['registrar']);
        $accounting = $this->user(['accounting']);
        $teacher = $this->user(['teacher']);
        $guardian = $this->user(['guardian']);
        $otherGuardian = $this->user(['guardian']);
        $studentUser = $this->user(['student']);
        [$enrollment,$guardianProfile] = $this->enrollment($guardian);
        GuardianProfile::query()->create(['user_id' => $otherGuardian->id, 'first_name' => 'Other', 'last_name' => 'Guardian']);
        $case = SupportCase::query()->create(['enrollment_id' => $enrollment->id, 'status' => 'open', 'opened_by' => $admin->id, 'opened_at' => now(), 'assigned_reviewer_id' => $registrar->id]);
        $this->get(route('support.queue'))->assertRedirect(route('identity.login'));
        $this->actingAs($accounting)->get(route('support.queue'))->assertForbidden();
        $this->actingAs($admin)->get(route('support.queue'))->assertOk()->assertSee($enrollment->student->full_name);
        $this->actingAs($registrar)->get(route('support.show', $case))->assertOk();
        $this->assertDatabaseHas('support_access_logs', ['support_case_id' => $case->id, 'actor_user_id' => $registrar->id, 'action' => 'view_raw']);
        $this->actingAs($otherRegistrar)->get(route('support.show', $case))->assertForbidden();
        $this->actingAs($teacher)->get(route('support.show', $case))->assertForbidden();
        $this->actingAs($guardian)->get(route('support.show', $case))->assertForbidden();
        $this->actingAs($studentUser)->get(route('support.show', $case))->assertForbidden();
        $this->actingAs($admin)->post(route('support.assign', $case), ['reviewer_user_id' => $otherRegistrar->id])->assertRedirect();
        $this->assertDatabaseHas('support_case_events', ['support_case_id' => $case->id, 'event_type' => 'reviewer_assigned', 'reviewer_user_id' => $otherRegistrar->id]);
        $this->actingAs($registrar)->post(route('support.assessment', $case), ['outcome' => 'approved'])->assertForbidden();
        $this->actingAs($otherRegistrar)->post(route('support.assessment', $case), ['outcome' => 'approved'])->assertRedirect();
        $this->assertSame('approved', $case->fresh()->final_outcome);
        $this->actingAs($guardian)->get(route('guardian.enrollment.progress'))->assertOk()->assertSee($enrollment->student->full_name)->assertDontSee('approved');
        $this->actingAs($otherGuardian)->get(route('guardian.enrollment.progress'))->assertOk()->assertDontSee($enrollment->student->full_name);
    }

    private function enrollment(User $guardianUser): array
    {
        $year = AcademicYear::query()->create(['code' => 'SUP', 'name' => 'Support test', 'starts_on' => '2026-06-01', 'ends_on' => '2027-03-31', 'status' => 'open']);
        $grade = GradeLevel::query()->create(['code' => 'SUP1', 'name' => 'Support Grade', 'sequence' => 1, 'is_active' => true]);
        $guardian = GuardianProfile::query()->create(['user_id' => $guardianUser->id, 'first_name' => 'Fictional', 'last_name' => 'Guardian']);
        $student = StudentProfile::query()->create(['first_name' => 'Fictional', 'last_name' => 'Student']);

        return [Enrollment::query()->create(['application_number' => 'SUP-'.Str::random(8), 'student_id' => $student->id, 'academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'applicant_guardian_id' => $guardian->id, 'student_category' => 'support_review', 'status' => 'assessment_pending', 'active_enrollment_key' => $student->id.':'.$year->id]), $guardian];
    }

    private function user(array $roles): User
    {
        $user = User::query()->create(['name' => 'Fictional '.Str::random(5), 'email' => Str::random(8).'@example.test', 'password' => Hash::make('Password12345'), 'status' => AccountStatus::Active, 'password_changed_at' => now()]);

        return app(AssignRolesAction::class)->execute($user, $roles, null);
    }
}
