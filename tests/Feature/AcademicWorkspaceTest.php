<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\Curriculum;
use App\Modules\Academics\Models\CurriculumGradeSubject;
use App\Modules\Academics\Models\CurriculumSectionTemplate;
use App\Modules\Academics\Models\CurriculumTemplateSchedule;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Academics\Support\OfficialClassProgramTemplate;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\StaffProfile;
use App\Modules\Requirements\Models\RequirementRule;
use Database\Seeders\Academics\GradeLevelSeeder;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AcademicWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $registrar;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-06 09:00:00');
        $this->seed(RoleSeeder::class);
        $this->admin = $this->user('admin');
        $this->registrar = $this->user('registrar');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_class_schedules_open_on_the_current_year_and_only_show_that_years_sections(): void
    {
        [$grade, $subject] = $this->gradeAndSubject();
        $older = $this->year('2025-2026', '2025-06-09', '2026-04-08', 'open');
        $current = $this->year('2026-2027', '2026-06-08', '2027-04-08', 'open');
        $this->year('2027-2028', '2027-06-07', '2028-04-07', 'planned');
        $oldSection = Section::query()->create(['academic_year_id' => $older->id, 'grade_level_id' => $grade->id, 'name' => 'Oldpaul', 'capacity' => 15]);
        $section = Section::query()->create(['academic_year_id' => $current->id, 'grade_level_id' => $grade->id, 'name' => 'Newpaul', 'capacity' => 15]);
        SectionSubject::query()->create(['section_id' => $section->id, 'subject_id' => $subject->id, 'days' => '1,2,3,4,5', 'starts_at' => '08:20', 'ends_at' => '09:00', 'schedule_text' => 'Monday-Friday 08:20-09:00']);

        $this->actingAs($this->admin)->get(route('academics.admin.index'))
            ->assertOk()
            ->assertSee('School Year 2026-2027')
            ->assertSee('2026-2027 (current)')
            ->assertSee('Newpaul')
            ->assertDontSee('Oldpaul')
            ->assertSee('No class program yet');

        $this->actingAs($this->admin)->get(route('academics.admin.index', ['year' => $older->public_id]))
            ->assertOk()->assertSee('Oldpaul')->assertDontSee('Newpaul');

        $this->actingAs($this->registrar)->get(route('academics.admin.index', ['year' => $current->public_id, 'section' => $section->public_id]))
            ->assertOk()
            ->assertSee('Grade 1 · Newpaul')
            ->assertSee('Good Manners and Right Conduct')
            ->assertSee('Mon-Fri')
            ->assertSee('08:20 – 09:00')
            ->assertSee('Unassigned')
            ->assertDontSee('Delete section Newpaul')
            ->assertDontSee('Archive this subject');

        // A section from another year is never shown through a forged filter.
        $this->actingAs($this->admin)->get(route('academics.admin.index', ['year' => $current->public_id, 'section' => $oldSection->public_id]))
            ->assertOk()->assertDontSee('Grade 1 · Oldpaul');
        $this->actingAs($this->admin)->get(route('academics.admin.index', ['year' => 'not-a-valid-ulid']))->assertSessionHasErrors('year');
    }

    public function test_old_bookmarks_and_tabs_point_to_the_new_screens(): void
    {
        $this->year('2026-2027', '2026-06-08', '2027-04-08', 'open');

        $this->actingAs($this->admin)->get(route('academics.admin.index'))
            ->assertOk()
            ->assertSee("['#school-years', '#grade-levels', '#subjects', '#sections']", false)
            ->assertSee(route('academics.admin.setup'), false)
            ->assertSee(route('academics.admin.curricula.index'), false);
        $this->actingAs($this->admin)->get(route('academics.admin.setup'))
            ->assertOk()->assertSee('id="school-years"', false)->assertSee('id="grade-levels"', false)
            ->assertSee('id="subjects"', false)->assertSee('id="sections"', false);
    }

    public function test_class_times_are_structured_validated_and_kept_in_sync_with_the_display_text(): void
    {
        [$grade, $subject] = $this->gradeAndSubject();
        $year = $this->year('2026-2027', '2026-06-08', '2027-04-08', 'open');
        $section = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Paul', 'capacity' => 15]);

        $this->actingAs($this->registrar)->post(route('academics.admin.section-subjects.store'), [
            'section_id' => $section->id, 'subject_id' => $subject->id, 'days' => [1, 3, 5], 'starts_at' => '07:30', 'ends_at' => '08:15',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $offering = SectionSubject::query()->sole();
        $this->assertSame('1,3,5', $offering->days);
        $this->assertSame('Monday, Wednesday, Friday 07:30-08:15', $offering->schedule_text);
        $this->assertNull($offering->curriculum_id);

        $this->actingAs($this->admin)->patch(route('academics.admin.section-subjects.update', $offering), [
            'days' => [1, 2, 3, 4, 5], 'starts_at' => '09:00', 'ends_at' => '08:00',
        ])->assertSessionHasErrors('ends_at');
        $this->assertSame('07:30:00', $offering->fresh()->starts_at);

        $this->actingAs($this->admin)->patch(route('academics.admin.section-subjects.update', $offering), [
            'days' => [9], 'starts_at' => '09:00', 'ends_at' => '09:40',
        ])->assertSessionHasErrors('days.0');

        // Older single-digit free text is now recognized instead of silently skipping conflict checks.
        $this->actingAs($this->admin)->patch(route('academics.admin.section-subjects.update', $offering), [
            'schedule_text' => 'Mon-Fri 8:20-9:00',
        ])->assertSessionHasNoErrors();
        $offering->refresh();
        $this->assertSame('1,2,3,4,5', $offering->days);
        $this->assertSame('08:20:00', $offering->starts_at);
        $this->assertSame('Mon-Fri 8:20-9:00', $offering->schedule_text);
        $this->assertDatabaseHas('audit_logs', ['action' => 'academics.section_subject.updated']);
    }

    public function test_teacher_conflicts_respect_class_days(): void
    {
        [$grade, $subject] = $this->gradeAndSubject();
        $year = $this->year('2026-2027', '2026-06-08', '2027-04-08', 'open');
        $staff = $this->teacher('Teacher Cruz');
        $first = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Paul', 'capacity' => 15]);
        $second = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Philip', 'capacity' => 15]);
        $monWed = SectionSubject::query()->create(['section_id' => $first->id, 'subject_id' => $subject->id, 'days' => '1,3', 'starts_at' => '08:00', 'ends_at' => '09:00']);
        $tueThu = SectionSubject::query()->create(['section_id' => $second->id, 'subject_id' => $subject->id, 'days' => '2,4', 'starts_at' => '08:00', 'ends_at' => '09:00']);

        $payload = fn (Section $section, SectionSubject $offering): array => [
            'staff_profile_id' => $staff->id, 'section_id' => $section->id, 'section_subject_id' => $offering->id,
            'assignment_type' => 'subject_teacher', 'is_active' => true,
        ];
        $this->actingAs($this->admin)->post(route('academics.admin.teaching-assignments.store'), $payload($first, $monWed))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('academics.admin.teaching-assignments.store'), $payload($second, $tueThu))->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->patch(route('academics.admin.section-subjects.update', $tueThu), [
            'days' => [3], 'starts_at' => '08:30', 'ends_at' => '09:10',
        ])->assertSessionHasErrors('schedule_text');
        $this->assertSame('2,4', $tueThu->fresh()->days);
    }

    public function test_changing_a_teacher_replaces_the_active_assignment_and_keeps_history(): void
    {
        [$grade, $subject] = $this->gradeAndSubject();
        $year = $this->year('2026-2027', '2026-06-08', '2027-04-08', 'open');
        $section = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Paul', 'capacity' => 15]);
        $offering = SectionSubject::query()->create(['section_id' => $section->id, 'subject_id' => $subject->id]);
        $first = $this->teacher('Teacher One');
        $second = $this->teacher('Teacher Two');
        $assign = fn (StaffProfile $staff): array => [
            'staff_profile_id' => $staff->id, 'section_id' => $section->id, 'section_subject_id' => $offering->id,
            'assignment_type' => 'subject_teacher', 'is_active' => true, 'replace_existing' => true,
        ];

        $this->actingAs($this->registrar)->post(route('academics.admin.teaching-assignments.store'), $assign($first))->assertSessionHasNoErrors();
        $this->actingAs($this->registrar)->post(route('academics.admin.teaching-assignments.store'), $assign($second))->assertSessionHasNoErrors();
        $this->assertFalse(TeachingAssignment::query()->where('staff_profile_id', $first->id)->sole()->is_active);
        $this->assertTrue(TeachingAssignment::query()->where('staff_profile_id', $second->id)->sole()->is_active);

        $this->actingAs($this->registrar)->post(route('academics.admin.teaching-assignments.store'), $assign($first))->assertSessionHasNoErrors();
        $this->assertSame(2, TeachingAssignment::query()->count());
        $this->assertTrue(TeachingAssignment::query()->where('staff_profile_id', $first->id)->sole()->is_active);
        $this->assertFalse(TeachingAssignment::query()->where('staff_profile_id', $second->id)->sole()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'academics.teaching_assignment.replaced']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'academics.teaching_assignment.reactivated']);

        // Advisers follow the same one-active-adviser rule.
        $this->actingAs($this->admin)->post(route('academics.admin.teaching-assignments.store'), [
            'staff_profile_id' => $first->id, 'section_id' => $section->id, 'assignment_type' => 'adviser', 'is_active' => true, 'replace_existing' => true,
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('academics.admin.teaching-assignments.store'), [
            'staff_profile_id' => $second->id, 'section_id' => $section->id, 'assignment_type' => 'adviser', 'is_active' => true, 'replace_existing' => true,
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, TeachingAssignment::query()->where('assignment_type', 'adviser')->where('is_active', true)->count());
    }

    public function test_only_the_admin_can_archive_and_restore_a_section_subject(): void
    {
        [$grade, $subject] = $this->gradeAndSubject();
        $year = $this->year('2026-2027', '2026-06-08', '2027-04-08', 'open');
        $section = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Paul', 'capacity' => 15]);
        $offering = SectionSubject::query()->create(['section_id' => $section->id, 'subject_id' => $subject->id]);
        $staff = $this->teacher('Teacher One');
        $assignment = TeachingAssignment::query()->create(['staff_profile_id' => $staff->id, 'section_id' => $section->id, 'section_subject_id' => $offering->id, 'assignment_type' => 'subject_teacher', 'is_active' => true]);
        $url = route('academics.admin.section-subjects.status', $offering);

        $this->app['auth']->forgetGuards();
        $this->patch($url, ['status' => 'archived'])->assertRedirect(route('identity.login'));
        $this->actingAs($this->registrar)->patch($url, ['status' => 'archived'])->assertForbidden();
        $this->actingAs($this->user('teacher'))->patch($url, ['status' => 'archived'])->assertForbidden();
        $this->actingAs($this->admin)->patch($url, ['status' => 'bogus'])->assertSessionHasErrors('status');

        $this->actingAs($this->admin)->patch($url, ['status' => 'archived'])->assertSessionHasNoErrors();
        $this->assertSame('archived', $offering->fresh()->status);
        $this->assertFalse($assignment->fresh()->is_active);
        $this->assertCount(0, $section->fresh()->sectionSubjects);
        $this->assertCount(1, $section->fresh()->allSectionSubjects);
        $this->assertDatabaseHas('audit_logs', ['action' => 'academics.section_subject.archived']);

        $this->actingAs($this->admin)->post(route('academics.admin.teaching-assignments.store'), [
            'staff_profile_id' => $staff->id, 'section_id' => $section->id, 'section_subject_id' => $offering->id,
            'assignment_type' => 'subject_teacher', 'is_active' => true, 'replace_existing' => true,
        ])->assertSessionHasErrors('section_subject_id');

        $this->actingAs($this->admin)->patch($url, ['status' => 'active'])->assertSessionHasNoErrors();
        $this->assertSame('active', $offering->fresh()->status);
        $this->assertCount(1, $section->fresh()->sectionSubjects);
    }

    public function test_only_the_admin_can_change_archive_state_through_edit_forms(): void
    {
        [$grade, $subject] = $this->gradeAndSubject();
        $gradePayload = ['code' => 'G1', 'name' => 'Grade 1', 'sequence' => 4, 'school_stage' => 'Elementary', 'next_grade_level_id' => null];

        $this->actingAs($this->registrar)->patch(route('academics.admin.grade-levels.update', $grade), [...$gradePayload, 'is_active' => false])->assertForbidden();
        $this->actingAs($this->registrar)->patch(route('academics.admin.grade-levels.update', $grade), [...$gradePayload, 'name' => 'Grade One', 'is_active' => true])->assertRedirect();
        $this->assertSame('Grade One', $grade->fresh()->name);
        $this->actingAs($this->registrar)->patch(route('academics.admin.subjects.update', $subject), ['code' => 'GMRC', 'name' => 'GMRC', 'is_active' => false])->assertForbidden();
        $this->actingAs($this->admin)->patch(route('academics.admin.subjects.update', $subject), ['code' => 'GMRC', 'name' => 'GMRC', 'is_active' => false])->assertRedirect();
        $this->assertFalse($subject->fresh()->is_active);

        $year = $this->year('2026-2027', '2026-06-08', '2027-04-08', 'open');
        $yearPayload = ['code' => '2026-2027', 'name' => 'School Year 2026-2027', 'starts_on' => '2026-06-08', 'ends_on' => '2027-04-08'];
        $this->actingAs($this->registrar)->patch(route('academics.admin.academic-years.update', $year), [...$yearPayload, 'status' => 'archived'])->assertForbidden();
        $this->actingAs($this->registrar)->patch(route('academics.admin.academic-years.update', $year), [...$yearPayload, 'status' => 'closed'])->assertRedirect();
        $this->actingAs($this->admin)->patch(route('academics.admin.academic-years.update', $year), [...$yearPayload, 'status' => 'archived'])->assertRedirect();
        $this->assertSame('archived', $year->fresh()->status);

        $section = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Paul', 'capacity' => 15]);
        $sectionPayload = ['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Paul', 'capacity' => 15];
        $this->actingAs($this->registrar)->patch(route('academics.admin.sections.update', $section), [...$sectionPayload, 'status' => 'inactive'])->assertForbidden();
        $this->actingAs($this->admin)->patch(route('academics.admin.sections.update', $section), [...$sectionPayload, 'status' => 'inactive'])->assertRedirect();
        $this->assertSame('inactive', $section->fresh()->status);
    }

    public function test_unused_records_can_be_deleted_by_the_admin_with_confirmation(): void
    {
        [$grade, $subject] = $this->gradeAndSubject();
        $this->actingAs($this->admin)->post(route('academics.admin.academic-years.store'), [
            'code' => '2030-2031', 'name' => 'Typo year', 'starts_on' => '2030-06-01', 'ends_on' => '2031-04-01', 'status' => 'planned',
        ])->assertRedirect();
        $year = AcademicYear::query()->where('code', '2030-2031')->sole();
        $this->assertGreaterThan(0, RequirementRule::query()->where('academic_year_id', $year->id)->count());
        $section = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Mistake', 'capacity' => 15]);
        SectionSubject::query()->create(['section_id' => $section->id, 'subject_id' => $subject->id]);
        $spare = Subject::query()->create(['code' => 'TYPO', 'name' => 'Typo subject', 'is_active' => true]);

        $this->app['auth']->forgetGuards();
        $this->delete(route('academics.admin.sections.destroy', $section), ['confirm_deletion' => '1'])->assertRedirect(route('identity.login'));
        $this->actingAs($this->registrar)->delete(route('academics.admin.sections.destroy', $section), ['confirm_deletion' => '1'])->assertForbidden();
        $this->actingAs($this->admin)->delete(route('academics.admin.sections.destroy', $section))->assertSessionHasErrors('confirm_deletion');
        $this->assertModelExists($section);

        $this->actingAs($this->admin)->delete(route('academics.admin.sections.destroy', $section), ['confirm_deletion' => '1'])
            ->assertRedirect(route('academics.admin.index', ['year' => $year->public_id, 'grade' => $grade->public_id]));
        $this->assertModelMissing($section);
        $this->assertDatabaseCount('section_subjects', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'academics.section.deleted', 'actor_user_id' => $this->admin->id]);

        $this->actingAs($this->admin)->delete(route('academics.admin.subjects.destroy', $spare), ['confirm_deletion' => '1'])->assertSessionHasNoErrors();
        $this->assertModelMissing($spare);

        $this->actingAs($this->admin)->delete(route('academics.admin.academic-years.destroy', $year), ['confirm_deletion' => '1'])
            ->assertRedirect(route('academics.admin.setup'));
        $this->assertModelMissing($year);
        $this->assertSame(0, RequirementRule::query()->where('academic_year_id', $year->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'requirements.rules.removed_with_unused_year']);
    }

    public function test_records_in_use_cannot_be_deleted_and_explain_why(): void
    {
        [$grade, $subject] = $this->gradeAndSubject();
        $year = $this->year('2026-2027', '2026-06-08', '2027-04-08', 'open');
        $section = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Paul', 'capacity' => 15]);
        $offering = SectionSubject::query()->create(['section_id' => $section->id, 'subject_id' => $subject->id]);
        $staff = $this->teacher('Teacher One');
        TeachingAssignment::query()->create(['staff_profile_id' => $staff->id, 'section_id' => $section->id, 'section_subject_id' => $offering->id, 'assignment_type' => 'subject_teacher', 'is_active' => true]);

        $this->actingAs($this->admin)->delete(route('academics.admin.sections.destroy', $section), ['confirm_deletion' => '1'])
            ->assertSessionHasErrors(['delete' => 'Section Paul is still used by 1 teaching assignments. Archive it instead so that history is kept.']);
        $this->actingAs($this->admin)->delete(route('academics.admin.section-subjects.destroy', $offering), ['confirm_deletion' => '1'])->assertSessionHasErrors('delete');
        $this->actingAs($this->admin)->delete(route('academics.admin.subjects.destroy', $subject), ['confirm_deletion' => '1'])->assertSessionHasErrors('delete');
        $this->actingAs($this->admin)->delete(route('academics.admin.grade-levels.destroy', $grade), ['confirm_deletion' => '1'])->assertSessionHasErrors('delete');
        $this->actingAs($this->admin)->delete(route('academics.admin.academic-years.destroy', $year), ['confirm_deletion' => '1'])->assertSessionHasErrors('delete');
        $this->assertModelExists($section);
        $this->assertModelExists($offering);
        $this->assertModelExists($subject);
        $this->assertModelExists($grade);
        $this->assertModelExists($year);

        $this->actingAs($this->admin)->get(route('academics.admin.index', ['year' => $year->public_id, 'section' => $section->public_id]))
            ->assertOk()->assertSee("Can't be deleted:", false)->assertSee('1 teaching assignments');
    }

    public function test_sections_in_use_cannot_move_to_another_year_or_grade(): void
    {
        [$grade] = $this->gradeAndSubject();
        $otherGrade = GradeLevel::query()->create(['code' => 'G2', 'name' => 'Grade 2', 'sequence' => 5, 'school_stage' => 'Elementary', 'is_active' => true]);
        $year = $this->year('2026-2027', '2026-06-08', '2027-04-08', 'open');
        $free = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Free', 'capacity' => 15]);
        $used = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Used', 'capacity' => 15]);
        TeachingAssignment::query()->create(['staff_profile_id' => $this->teacher('Adviser')->id, 'section_id' => $used->id, 'assignment_type' => 'adviser', 'is_active' => true]);
        $payload = fn (string $name): array => ['academic_year_id' => $year->id, 'grade_level_id' => $otherGrade->id, 'name' => $name, 'capacity' => 15, 'status' => 'active'];

        $this->actingAs($this->registrar)->patch(route('academics.admin.sections.update', $free), $payload('Free'))->assertSessionHasNoErrors();
        $this->assertSame($otherGrade->id, $free->fresh()->grade_level_id);
        $this->actingAs($this->registrar)->patch(route('academics.admin.sections.update', $used), $payload('Used'))->assertSessionHasErrors('grade_level_id');
        $this->assertSame($grade->id, $used->fresh()->grade_level_id);
    }

    public function test_extra_sections_and_missing_subjects_come_from_the_published_curriculum(): void
    {
        [$grade, $subject] = $this->gradeAndSubject();
        $math = Subject::query()->create(['code' => 'MATH', 'name' => 'Mathematics', 'is_active' => true]);
        $year = $this->year('2026-2027', '2026-06-08', '2027-04-08', 'open');
        $curriculum = Curriculum::query()->create(['academic_year_id' => $year->id, 'version' => 1, 'name' => 'Published', 'status' => 'published', 'published_at' => now(), 'published_by' => $this->admin->id]);
        CurriculumGradeSubject::query()->create(['curriculum_id' => $curriculum->id, 'grade_level_id' => $grade->id, 'subject_id' => $subject->id, 'sort_order' => 1]);
        CurriculumGradeSubject::query()->create(['curriculum_id' => $curriculum->id, 'grade_level_id' => $grade->id, 'subject_id' => $math->id, 'sort_order' => 2]);
        $template = CurriculumSectionTemplate::query()->create(['curriculum_id' => $curriculum->id, 'grade_level_id' => $grade->id, 'name' => 'Paul', 'capacity' => 15]);
        CurriculumTemplateSchedule::query()->create(['curriculum_section_template_id' => $template->id, 'subject_id' => $math->id, 'days' => '1,2,3,4,5', 'starts_at' => '10:00', 'ends_at' => '10:40', 'schedule_text' => 'Monday-Friday 10:00-10:40']);
        $paul = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'curriculum_id' => $curriculum->id, 'name' => 'Paul', 'capacity' => 15]);
        SectionSubject::query()->create(['section_id' => $paul->id, 'subject_id' => $subject->id, 'curriculum_id' => $curriculum->id]);

        $this->actingAs($this->admin)->get(route('academics.admin.index', ['year' => $year->public_id, 'section' => $paul->public_id]))
            ->assertOk()->assertSee('Add 1 missing curriculum subject')->assertSee('Changed from curriculum');
        $this->actingAs($this->registrar)->post(route('academics.admin.sections.curriculum-subjects.store', $paul))->assertSessionHas('status', '1 missing curriculum subject added.');
        $this->assertDatabaseHas('section_subjects', ['section_id' => $paul->id, 'subject_id' => $math->id, 'curriculum_id' => $curriculum->id, 'schedule_text' => 'Monday-Friday 10:00-10:40']);
        $this->actingAs($this->registrar)->post(route('academics.admin.sections.curriculum-subjects.store', $paul))->assertSessionHas('status', 'This section already offers every subject in the published curriculum.');
        $this->assertSame(2, SectionSubject::query()->where('section_id', $paul->id)->count());

        $this->actingAs($this->registrar)->post(route('academics.admin.sections.store'), [
            'academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Barnabas', 'capacity' => 12, 'status' => 'active',
        ])->assertRedirect();
        $extra = Section::query()->where('name', 'Barnabas')->sole();
        $this->assertNull($extra->curriculum_id);
        $this->assertSame(2, SectionSubject::query()->where('section_id', $extra->id)->count());
        $this->actingAs($this->registrar)->post(route('academics.admin.sections.store'), [
            'academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Silas', 'capacity' => 12, 'status' => 'inactive',
        ])->assertForbidden();
    }

    public function test_curriculum_tab_defaults_to_the_current_year_and_publishing_opens_class_schedules(): void
    {
        $this->seed(GradeLevelSeeder::class);
        $this->year('2025-2026', '2025-06-09', '2026-04-08', 'closed');
        $year = $this->year('2026-2027', '2026-06-08', '2027-04-08', 'open');

        $this->actingAs($this->admin)->get(route('academics.admin.curricula.index'))
            ->assertOk()
            ->assertSee('Class program for 2026-2027')
            ->assertSee('value="JCA Class Program 2026-2027"', false)
            ->assertSee('value="'.OfficialClassProgramTemplate::SOURCE_KEY.'" selected', false);

        $this->actingAs($this->admin)->post(route('academics.admin.curricula.store'), [
            'academic_year_id' => $year->id, 'name' => 'JCA Class Program 2026-2027', 'source_curriculum_id' => OfficialClassProgramTemplate::SOURCE_KEY,
        ])->assertRedirect();
        $draft = Curriculum::query()->sole();
        $this->assertTrue(DB::table('curriculum_template_schedules')->whereNotNull('starts_at')->exists());
        $this->actingAs($this->admin)->get(route('academics.admin.index'))->assertOk()->assertSee('Review and publish');

        $this->actingAs($this->admin)->post(route('academics.admin.curricula.publish', $draft))
            ->assertRedirect(route('academics.admin.index', ['year' => $year->public_id]));
        $this->assertSame(20, Section::query()->where('academic_year_id', $year->id)->count());
        $this->assertSame(0, SectionSubject::query()->whereNull('starts_at')->count());

        $paul = Section::query()->where('name', 'Paul')->sole();
        $this->actingAs($this->admin)->get(route('academics.admin.index', ['year' => $year->public_id, 'section' => $paul->public_id]))
            ->assertOk()->assertSee('Matches curriculum')->assertSee('Reading and Literacy')->assertSee('09:00 – 09:40');
        $this->actingAs($this->admin)->get(route('academics.admin.index', ['year' => $year->public_id, 'grade' => $paul->gradeLevel->public_id]))
            ->assertOk()->assertSee('Grade 1 sections')->assertSee('Philip')->assertDontSee('Jacob');
    }

    /** @return array{GradeLevel, Subject} */
    private function gradeAndSubject(): array
    {
        return [
            GradeLevel::query()->create(['code' => 'G1', 'name' => 'Grade 1', 'sequence' => 4, 'school_stage' => 'Elementary', 'is_active' => true]),
            Subject::query()->create(['code' => 'GMRC', 'name' => 'Good Manners and Right Conduct', 'is_active' => true]),
        ];
    }

    private function year(string $code, string $starts, string $ends, string $status): AcademicYear
    {
        return AcademicYear::query()->create(['code' => $code, 'name' => 'School Year '.$code, 'starts_on' => $starts, 'ends_on' => $ends, 'status' => $status]);
    }

    private function teacher(string $name): StaffProfile
    {
        $user = $this->user('teacher');
        $user->update(['name' => $name]);

        return StaffProfile::query()->create(['user_id' => $user->id, 'active_from' => now()->subDay()]);
    }

    private function user(string $role): User
    {
        $user = User::query()->create([
            'name' => ucfirst($role).' '.Str::random(8),
            'email' => Str::lower(Str::random(12)).'@example.test',
            'password' => Hash::make('Password12345'),
            'status' => AccountStatus::Active,
            'password_changed_at' => now(),
        ]);

        return app(AssignRolesAction::class)->execute($user, [$role], null);
    }
}
