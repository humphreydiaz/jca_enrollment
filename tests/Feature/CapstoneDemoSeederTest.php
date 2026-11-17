<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Billing\Models\ExamPermit;
use App\Modules\Billing\Models\Payment;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Actions\AuthenticateUserAction;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\StaffProfile;
use App\Modules\StudentSupport\Models\ApprovedSupportSummary;
use Database\Seeders\Capstone\CapstoneDemoSeeder;
use Database\Seeders\Capstone\CapstoneRoster;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class CapstoneDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_a_lived_in_school_year_and_keeps_the_snapshotted_staff(): void
    {
        Storage::fake('local');
        // The accounts that exist before the rebuild.
        $this->seed(RoleSeeder::class);
        $admin = User::query()->create(['name' => 'Reina Dela Rosa', 'email' => 'reina@example.org', 'mobile' => '09171234567', 'password' => 'Old-Password-123', 'status' => 'active']);
        app(AssignRolesAction::class)->execute($admin, ['admin'], null);
        StaffProfile::query()->create(['user_id' => $admin->id, 'employee_number' => '26-JCA-555', 'position_title' => 'School Administrator']);
        $this->artisan('capstone:snapshot-staff')->assertSuccessful();
        Storage::disk('local')->assertExists(CapstoneDemoSeeder::SNAPSHOT_PATH);

        // migrate:fresh stands for wiping the old accounts (RefreshDatabase keeps the schema).
        foreach (['audit_logs', 'staff_profiles', 'user_roles', 'users'] as $table) {
            DB::table($table)->delete();
        }
        $this->seed(CapstoneDemoSeeder::class);

        // Same Admin (name, contact, Staff ID), new shared password; default Registrar and Accounting.
        $rebuilt = User::query()->where('email', 'reina@example.org')->sole();
        $this->assertSame('Reina Dela Rosa', $rebuilt->name);
        $this->assertSame('+639171234567', $rebuilt->mobile);
        $this->assertTrue($rebuilt->hasRole('admin'));
        $this->assertSame('26-JCA-555', $rebuilt->staffProfile->employee_number);
        $this->assertSame($rebuilt->id, app(AuthenticateUserAction::class)->execute('26-JCA-555', CapstoneDemoSeeder::PASSWORD)?->id);
        $this->assertTrue(User::query()->where('email', 'jenny@gmail.com')->sole()->hasRole('registrar'));
        $this->assertSame('26-JCA-102', User::query()->where('email', 'angelica@gmail.com')->sole()->staffProfile->employee_number);
        Storage::disk('local')->assertMissing(CapstoneDemoSeeder::SNAPSHOT_PATH);

        // A year with every curriculum subject staffed.
        $year = AcademicYear::query()->sole();
        $this->assertSame('open', $year->status);
        $this->assertSame(SectionSubject::query()->count(), TeachingAssignment::query()->where('assignment_type', 'subject_teacher')->count());
        $this->assertSame(20, TeachingAssignment::query()->where('assignment_type', 'adviser')->count());

        // Learners: enrolled with every requirement received, plus two still in progress.
        $this->assertSame(count(CapstoneRoster::LEARNERS), Enrollment::query()->where('status', 'enrolled')->count());
        $this->assertSame(['draft', 'under_review'], Enrollment::query()->whereNotIn('status', ['enrolled'])->orderBy('status')->pluck('status')->all());
        $this->assertSame(0, DB::table('enrollment_requirements')->join('enrollments', 'enrollments.id', '=', 'enrollment_requirements.enrollment_id')
            ->where('enrollments.status', 'enrolled')->whereNull('enrollment_requirements.received_at')->count());
        $this->assertSame(3, ApprovedSupportSummary::query()->count());
        $this->assertSame(0, GuardianStudent::query()->whereNotNull('verified_at')->where('may_manage_enrollment', true)->where('may_view_financials', false)->count());

        // Money and permits that look like months of use, never dated in the future.
        $this->assertGreaterThan(40, Payment::query()->where('status', 'posted')->count());
        $this->assertTrue(Payment::query()->max('received_on') <= '2026-10-06');
        $this->assertGreaterThan(10, ExamPermit::query()->count());
        $this->assertTrue(DB::table('audit_logs')->max('created_at') <= '2026-10-06 17:00:00');

        // Nobody fictional can be texted or mailed.
        $this->assertSame(0, User::query()->whereNotIn('email', ['reina@example.org', 'jenny@gmail.com', 'angelica@gmail.com'])->where('email', 'not like', '%@gmail.test')->whereNotNull('email')->count());
        $this->assertSame(0, DB::table('guardian_profiles')->whereNotNull('mobile')->count());
        $this->assertSame(0, DB::table('student_profiles')->whereNotNull('mobile')->count());

        // A second run never stacks a duplicate year on top.
        $this->expectException(RuntimeException::class);
        $this->seed(CapstoneDemoSeeder::class);
    }
}
