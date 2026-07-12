<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\SectionAssignment\Actions\ConfirmSectionAssignmentAction;
use App\Modules\SectionAssignment\Actions\TransferSectionAssignmentAction;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SectionAssignmentConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_mysql_section_row_locks_serialize_confirmation_and_transfer(): void
    {
        $this->seed(RoleSeeder::class);
        $registrar = $this->user();
        $year = AcademicYear::query()->create(['code' => 'LOCK-Y', 'name' => 'Lock year', 'starts_on' => '2026-06-01', 'ends_on' => '2027-03-31', 'status' => 'open']);
        $grade = GradeLevel::query()->create(['code' => 'LOCK-G', 'name' => 'Lock grade', 'sequence' => 1, 'is_active' => true]);
        $sourceSection = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Source', 'capacity' => 1, 'status' => 'active']);
        $destination = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Destination', 'capacity' => 1, 'status' => 'active']);
        $first = $this->enrollment($year, $grade);

        config(['database.connections.section_lock_holder' => config('database.connections.mysql')]);
        $holder = DB::connection('section_lock_holder');
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

        $holder->beginTransaction();
        $holder->select('SELECT id FROM sections WHERE id = ? FOR UPDATE', [$sourceSection->id]);
        try {
            app(ConfirmSectionAssignmentAction::class)->execute($first, $sourceSection, $registrar, false);
            $this->fail('Confirmation must wait for the destination section row lock.');
        } catch (QueryException) {
            $this->assertDatabaseCount('section_assignments', 0);
        } finally {
            $holder->rollBack();
        }

        $assignment = app(ConfirmSectionAssignmentAction::class)->execute($first, $sourceSection, $registrar, false);
        $second = $this->enrollment($year, $grade);
        try {
            app(ConfirmSectionAssignmentAction::class)->execute($second, $sourceSection, $registrar, false);
            $this->fail('Serialized confirmation must recheck capacity.');
        } catch (ValidationException) {
            $this->assertSame(1, $sourceSection->sectionAssignments()->where('status', 'confirmed')->whereNull('ended_at')->count());
        }

        $holder->beginTransaction();
        $holder->select('SELECT id FROM sections WHERE id = ? FOR UPDATE', [$destination->id]);
        try {
            app(TransferSectionAssignmentAction::class)->execute($assignment, $destination, $registrar, 'Concurrent fictional transfer', false);
            $this->fail('Transfer must wait for the destination section row lock.');
        } catch (QueryException) {
            $this->assertNull($assignment->fresh()->ended_at);
        } finally {
            $holder->rollBack();
            DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
            DB::disconnect('section_lock_holder');
        }

        $transferred = app(TransferSectionAssignmentAction::class)->execute($assignment->fresh(), $destination, $registrar, 'Completed fictional transfer', false);
        $this->assertSame($assignment->id, $transferred->transferred_from_id);
        $this->assertNotNull($assignment->fresh()->ended_at);
    }

    private function enrollment(AcademicYear $year, GradeLevel $grade): Enrollment
    {
        $student = StudentProfile::query()->create(['first_name' => 'Lock', 'last_name' => Str::random(8)]);
        $guardian = GuardianProfile::query()->create(['first_name' => 'Lock', 'last_name' => 'Guardian']);

        return Enrollment::query()->create(['application_number' => 'LOCK-'.Str::upper(Str::random(8)), 'student_id' => $student->id, 'academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'applicant_guardian_id' => $guardian->id, 'student_category' => 'regular', 'status' => 'approved', 'active_enrollment_key' => $student->id.':'.$year->id, 'approved_at' => now()]);
    }

    private function user(): User
    {
        $user = User::query()->create(['name' => 'Lock Registrar', 'email' => Str::lower(Str::random(8)).'@example.test', 'password' => Hash::make('Password12345'), 'status' => AccountStatus::Active, 'password_changed_at' => now()]);

        return app(AssignRolesAction::class)->execute($user, ['registrar'], null);
    }
}
