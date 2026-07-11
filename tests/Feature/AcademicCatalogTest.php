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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AcademicCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_only_admin_and_registrar_can_maintain_the_academic_catalog(): void
    {
        $admin = $this->activeUser(['admin']);
        $registrar = $this->activeUser(['registrar']);
        $teacher = $this->activeUser(['teacher']);
        $guardian = $this->activeUser(['guardian']);

        $this->get(route('academics.admin.index'))->assertRedirect(route('identity.login'));
        $this->actingAs($guardian)->get(route('academics.admin.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('academics.admin.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('academics.admin.index'))
            ->assertOk()
            ->assertSee('Academic catalog and teaching assignments')
            ->assertSee('xl:grid-cols-2');
        $this->actingAs($registrar)->get(route('academics.admin.index'))->assertOk();
    }

    public function test_admin_can_create_and_update_catalog_records_with_default_section_capacity(): void
    {
        $admin = $this->activeUser(['admin']);
        $this->actingAs($admin)->post(route('academics.admin.academic-years.store'), $this->yearPayload())
            ->assertRedirect();
        $year = AcademicYear::query()->firstOrFail();

        $this->actingAs($admin)->post(route('academics.admin.grade-levels.store'), [
            'code' => 'G1', 'name' => 'Grade 1', 'sequence' => 1, 'school_stage' => 'Elementary', 'is_active' => true,
        ])->assertRedirect();
        $grade = GradeLevel::query()->firstOrFail();

        $this->actingAs($admin)->post(route('academics.admin.subjects.store'), [
            'code' => 'ENG', 'name' => 'English', 'description' => 'Language', 'is_active' => true,
        ])->assertRedirect();
        $subject = Subject::query()->firstOrFail();

        $this->actingAs($admin)->post(route('academics.admin.sections.store'), [
            'academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Faith', 'capacity' => 15, 'sort_order' => 1, 'status' => 'active',
        ])->assertRedirect();
        $section = Section::query()->firstOrFail();
        $this->assertSame(15, $section->capacity);

        $this->actingAs($admin)->post(route('academics.admin.section-subjects.store'), [
            'section_id' => $section->id, 'subject_id' => $subject->id, 'schedule_text' => 'Mon 8:00 AM',
        ])->assertRedirect();
        $sectionSubject = SectionSubject::query()->firstOrFail();

        $this->actingAs($admin)->patch(route('academics.admin.section-subjects.update', $sectionSubject), [
            'schedule_text' => 'Tue 8:00 AM',
        ])->assertRedirect();
        $this->assertSame('Tue 8:00 AM', $sectionSubject->fresh()->schedule_text);

        $this->actingAs($admin)->patch(route('academics.admin.academic-years.update', $year), array_merge($this->yearPayload(), ['status' => 'open']))
            ->assertRedirect();
        $this->assertSame('open', $year->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'academics.section_subject.updated']);
    }

    public function test_mysql_unique_and_foreign_key_constraints_protect_catalog_history(): void
    {
        [$year, $grade] = $this->catalogParents();
        Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Faith', 'capacity' => 15]);

        try {
            Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Faith', 'capacity' => 15]);
            $this->fail('MySQL should reject duplicate section names in the same school year and grade.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->expectException(QueryException::class);
        $year->delete();
    }

    public function test_inactive_catalog_values_remain_visible_but_cannot_be_used_for_new_sections(): void
    {
        $admin = $this->activeUser(['admin']);
        [$year, $grade] = $this->catalogParents();

        $this->actingAs($admin)->patch(route('academics.admin.grade-levels.update', $grade), [
            'code' => $grade->code, 'name' => $grade->name, 'sequence' => $grade->sequence, 'school_stage' => null, 'next_grade_level_id' => null, 'is_active' => false,
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('academics.admin.sections.store'), [
            'academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Hope', 'capacity' => 15, 'status' => 'active',
        ])->assertSessionHasErrors('grade_level_id');

        $this->actingAs($admin)->get(route('academics.admin.index'))
            ->assertOk()
            ->assertSee('Inactive');
        $this->assertDatabaseHas('grade_levels', ['id' => $grade->id, 'is_active' => false]);
    }

    public function test_teacher_can_only_view_their_own_active_assignments(): void
    {
        $admin = $this->activeUser(['admin']);
        $teacher = $this->activeStaffUser('Teacher One');
        $otherTeacher = $this->activeStaffUser('Teacher Two');
        [$year, $grade] = $this->catalogParents();
        $subject = Subject::query()->create(['code' => 'SCI', 'name' => 'Science', 'is_active' => true]);
        $section = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Faith', 'capacity' => 15]);
        $otherSection = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Hope', 'capacity' => 15]);
        $sectionSubject = SectionSubject::query()->create(['section_id' => $section->id, 'subject_id' => $subject->id, 'schedule_text' => 'Mon 8:00 AM']);

        $this->actingAs($admin)->post(route('academics.admin.teaching-assignments.store'), [
            'staff_profile_id' => $teacher->staffProfile->id, 'section_id' => $section->id, 'section_subject_id' => $sectionSubject->id,
            'assignment_type' => 'subject_teacher', 'is_active' => true,
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('academics.admin.teaching-assignments.store'), [
            'staff_profile_id' => $otherTeacher->staffProfile->id, 'section_id' => $otherSection->id,
            'assignment_type' => 'adviser', 'is_active' => true,
        ])->assertRedirect();

        $assignment = TeachingAssignment::query()->where('staff_profile_id', $teacher->staffProfile->id)->firstOrFail();
        $otherAssignment = TeachingAssignment::query()->where('staff_profile_id', $otherTeacher->staffProfile->id)->firstOrFail();
        $this->assertTrue(Gate::forUser($teacher)->allows('view', $assignment));
        $this->assertFalse(Gate::forUser($teacher)->allows('view', $otherAssignment));

        $this->actingAs($teacher)->get(route('academics.teacher.assignments'))
            ->assertOk()
            ->assertSee('Faith')
            ->assertDontSee('Hope');
        $this->actingAs($teacher)->post(route('academics.admin.subjects.store'), [
            'code' => 'MATH', 'name' => 'Math', 'is_active' => true,
        ])->assertForbidden();
    }

    /** @return array{0: AcademicYear, 1: GradeLevel} */
    private function catalogParents(): array
    {
        return [
            AcademicYear::query()->create($this->yearPayload()),
            GradeLevel::query()->create(['code' => 'G1', 'name' => 'Grade 1', 'sequence' => 1, 'is_active' => true]),
        ];
    }

    /** @return array<string, mixed> */
    private function yearPayload(): array
    {
        return ['code' => '2026-2027', 'name' => 'School Year 2026-2027', 'starts_on' => '2026-06-01', 'ends_on' => '2027-03-31', 'status' => 'planned'];
    }

    /** @param list<string> $roleCodes */
    private function activeUser(array $roleCodes): User
    {
        $user = User::query()->create([
            'name' => 'User '.Str::random(10),
            'email' => Str::lower(Str::random(12)).'@example.test',
            'password' => Hash::make('Password12345'),
            'status' => AccountStatus::Active,
            'password_changed_at' => now(),
        ]);

        return app(AssignRolesAction::class)->execute($user, $roleCodes, null);
    }

    private function activeStaffUser(string $name): User
    {
        $user = $this->activeUser(['teacher']);
        $user->update(['name' => $name]);
        StaffProfile::query()->create(['user_id' => $user->id, 'active_from' => now()->subDay()]);

        return $user->fresh('staffProfile');
    }
}
