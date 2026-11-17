<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\StaffProfile;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/** D-068: schedule conflicts for the Registrar/Admin and the teacher's own weekly schedule. */
class ScheduleConflictsAndTeacherScheduleTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;

    private GradeLevel $grade;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->year = AcademicYear::query()->create(['code' => 'SC-'.Str::random(4), 'name' => 'School Year 2026-2027', 'starts_on' => now()->subMonth(), 'ends_on' => now()->addMonths(8), 'status' => 'open']);
        $this->grade = GradeLevel::query()->create(['code' => 'SC-G7', 'name' => 'Grade 7', 'sequence' => 9107, 'is_active' => true]);
    }

    public function test_registrar_sees_teacher_and_section_conflicts_and_unreadable_schedules(): void
    {
        [$teacher, $staff] = $this->teacher('Rosa Teacher');
        $faith = $this->section('Faith');
        $hope = $this->section('Hope');
        // Created directly, as old or edited data can be; the assignment guard never saw them together.
        $this->assign($staff, $this->offering($faith, 'Mathematics', '1,2,3,4,5', '08:00', '09:00'));
        $this->assign($staff, $this->offering($hope, 'Science', '1,3,5', '08:30', '09:30'));
        $this->offering($faith, 'English', '1,2,3,4,5', '08:30', '09:15');
        $this->offering($hope, 'Filipino', null, null, null, 'TBA');
        $this->offering($hope, 'MAPEH', '2,4', '10:00', '11:00');

        $page = $this->actingAs($this->user(['registrar']))->get(route('academics.schedule-conflicts'));
        $page->assertOk()
            ->assertSee('Teacher booked in two classes at once (1)')
            ->assertSee('Rosa Teacher')->assertSee('Grade 7 – Faith')->assertSee('Mathematics')->assertSee('Grade 7 – Hope')->assertSee('Science')
            ->assertSee('Two subjects of one section at once (1)')->assertSee('English')
            ->assertSee('Schedules that cannot be checked (1)')->assertSee('TBA')
            ->assertSee(route('academics.admin.index', ['year' => $this->year->public_id, 'section' => $faith->public_id]))
            ->assertDontSee('No conflicts in');

        $this->actingAs($this->user(['admin']))->get(route('academics.schedule-conflicts'))->assertOk();
        auth()->logout();
        $this->get(route('academics.schedule-conflicts'))->assertRedirect(route('identity.login'));
        foreach (['teacher', 'accounting', 'guardian'] as $role) {
            $this->actingAs($this->user([$role]))->get(route('academics.schedule-conflicts'))->assertForbidden();
        }
    }

    public function test_a_clean_year_says_there_are_no_conflicts(): void
    {
        $faith = $this->section('Faith');
        $this->offering($faith, 'Mathematics', '1,2,3,4,5', '08:00', '09:00');
        $this->offering($faith, 'English', '1,2,3,4,5', '09:00', '10:00');

        $this->actingAs($this->user(['registrar']))->get(route('academics.schedule-conflicts'))->assertOk()
            ->assertSee('No conflicts in School Year 2026-2027')->assertDontSee('Teacher booked in two classes');
    }

    public function test_a_teacher_sees_only_their_own_week_with_overlaps_flagged(): void
    {
        [$teacher, $staff] = $this->teacher('Rosa Teacher');
        [, $otherStaff] = $this->teacher('Other Teacher');
        $faith = $this->section('Faith');
        $hope = $this->section('Hope');
        $this->assign($staff, $this->offering($faith, 'Mathematics', '1,3', '08:00', '09:00'));
        $this->assign($staff, $this->offering($hope, 'Science', '1', '08:30', '09:30'));
        $this->assign($staff, $this->offering($hope, 'Values', '2', '13:00', '14:00'));
        $this->assign($staff, $this->offering($faith, 'Homeroom', null, null, null, 'To be announced'));
        $this->assign($otherStaff, $this->offering($faith, 'Secret Other Subject', '1', '10:00', '11:00'));
        TeachingAssignment::query()->create(['staff_profile_id' => $staff->id, 'section_id' => $faith->id, 'assignment_type' => 'adviser', 'is_active' => true]);

        $this->actingAs($teacher)->get(route('academics.my-schedule'))->assertOk()
            ->assertSee('2 of your classes overlap')
            ->assertSeeInOrder(['Monday', '08:00 – 09:00', 'Mathematics', 'Overlaps with Science (Grade 7 – Hope)', '08:30 – 09:30', 'Science', 'Tuesday', '13:00 – 14:00', 'Values', 'Wednesday', 'Mathematics'])
            ->assertSee('Classes without a set time')->assertSee('Homeroom')
            ->assertSeeText('Class adviser of: Grade 7 – Faith')
            ->assertDontSee('Secret Other Subject');

        $this->actingAs($this->user(['registrar']))->get(route('academics.my-schedule'))->assertForbidden();
    }

    private function section(string $name): Section
    {
        return Section::query()->create(['academic_year_id' => $this->year->id, 'grade_level_id' => $this->grade->id, 'name' => $name, 'capacity' => 30, 'status' => 'active']);
    }

    private function offering(Section $section, string $subject, ?string $days, ?string $starts, ?string $ends, ?string $text = null): SectionSubject
    {
        $subjectModel = Subject::query()->firstOrCreate(['code' => 'SC-'.Str::slug($subject)], ['name' => $subject]);

        return SectionSubject::query()->create(['section_id' => $section->id, 'subject_id' => $subjectModel->id, 'days' => $days, 'starts_at' => $starts, 'ends_at' => $ends, 'schedule_text' => $text, 'status' => 'active']);
    }

    private function assign(StaffProfile $staff, SectionSubject $offering): void
    {
        TeachingAssignment::query()->create(['staff_profile_id' => $staff->id, 'section_id' => $offering->section_id, 'section_subject_id' => $offering->id, 'assignment_type' => 'subject_teacher', 'is_active' => true]);
    }

    /** @return array{0: User, 1: StaffProfile} */
    private function teacher(string $name): array
    {
        $user = $this->user(['teacher']);
        $user->update(['name' => $name]);

        return [$user->fresh(), StaffProfile::query()->create(['user_id' => $user->id, 'employee_number' => '26-JCA-'.random_int(100, 999)])];
    }

    /** @param list<string> $roles */
    private function user(array $roles): User
    {
        $user = User::query()->create(['name' => 'Fictional '.Str::random(6), 'email' => Str::lower(Str::random(12)).'@example.test', 'password' => Hash::make('Password12345'), 'status' => AccountStatus::Active, 'password_changed_at' => now()]);

        return app(AssignRolesAction::class)->execute($user, $roles, null);
    }
}
