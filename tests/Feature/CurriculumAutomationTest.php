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
use App\Modules\Shared\Audit\Models\AuditLog;
use Database\Seeders\Academics\GradeLevelSeeder;
use Database\Seeders\Academics\OfficialClassProgram2026Seeder;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CurriculumAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_publishes_a_reviewed_curriculum_and_sections_receive_subjects_and_schedules(): void
    {
        $admin = $this->user('admin');
        [$year, $grade, $subject] = $this->catalog('2026-2027');

        $this->actingAs($admin)->post(route('academics.admin.curricula.store'), [
            'academic_year_id' => $year->id, 'name' => 'First reviewed curriculum',
        ])->assertRedirect();
        $curriculum = Curriculum::query()->firstOrFail();
        $this->actingAs($admin)->post(route('academics.admin.curricula.subjects.store', $curriculum), [
            'grade_level_id' => $grade->id, 'subject_id' => $subject->id,
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('academics.admin.curricula.sections.store', $curriculum), [
            'grade_level_id' => $grade->id, 'name' => 'Paul', 'capacity' => 15,
        ])->assertRedirect();
        $template = CurriculumSectionTemplate::query()->firstOrFail();
        $this->actingAs($admin)->post(route('academics.admin.curricula.schedules.store', [$curriculum, $template]), [
            'subject_id' => $subject->id, 'schedule_text' => 'Monday-Friday 08:20-09:00',
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('academics.admin.curricula.publish', $curriculum))->assertRedirect();

        $section = Section::query()->where('academic_year_id', $year->id)->where('name', 'Paul')->firstOrFail();
        $this->assertSame($curriculum->id, $section->curriculum_id);
        $this->assertDatabaseHas('section_subjects', [
            'section_id' => $section->id, 'subject_id' => $subject->id,
            'curriculum_id' => $curriculum->id, 'schedule_text' => 'Monday-Friday 08:20-09:00',
        ]);
        $this->assertSame('published', $curriculum->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'academics.curriculum.published']);

        $this->actingAs($admin)->post(route('academics.admin.curricula.subjects.store', $curriculum), [
            'grade_level_id' => $grade->id, 'subject_id' => $subject->id,
        ])->assertForbidden();
        $this->actingAs($admin)->patch(route('academics.admin.subjects.update', $subject), [
            'code' => 'GMRC', 'name' => 'Changed name', 'is_active' => true,
        ])->assertSessionHasErrors('name');
        $otherGrade = GradeLevel::query()->create(['code' => 'G2', 'name' => 'Grade 2', 'sequence' => 5, 'school_stage' => 'Elementary', 'is_active' => true]);
        $this->actingAs($admin)->patch(route('academics.admin.sections.update', $section), [
            'academic_year_id' => $year->id, 'grade_level_id' => $otherGrade->id, 'name' => 'Paul',
            'capacity' => 15, 'sort_order' => 1, 'status' => 'active',
        ])->assertSessionHasErrors('grade_level_id');
        $this->assertSame($grade->id, $section->fresh()->grade_level_id);

        // A curriculum section can still be renamed by the Admin; the rename is audited.
        $this->actingAs($admin)->patch(route('academics.admin.sections.update', $section), [
            'academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Saint Paul',
            'capacity' => 15, 'sort_order' => 1, 'status' => 'active',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Saint Paul', $section->fresh()->name);
        $this->assertSame($curriculum->id, $section->fresh()->curriculum_id);
        $this->assertSame('Paul', AuditLog::query()->where('action', 'academics.section.updated')->latest('id')->firstOrFail()->metadata['previous_name']);
    }

    public function test_copy_to_new_year_reuses_subjects_and_names_without_rewriting_older_year(): void
    {
        $admin = $this->user('admin');
        [$year, $grade, $subject] = $this->catalog('2026-2027');
        $original = Curriculum::query()->create(['academic_year_id' => $year->id, 'version' => 1, 'name' => 'Original', 'status' => 'published', 'published_at' => now(), 'published_by' => $admin->id]);
        CurriculumGradeSubject::query()->create(['curriculum_id' => $original->id, 'grade_level_id' => $grade->id, 'subject_id' => $subject->id]);
        CurriculumSectionTemplate::query()->create(['curriculum_id' => $original->id, 'grade_level_id' => $grade->id, 'name' => 'Paul', 'capacity' => 15]);
        $historical = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'curriculum_id' => $original->id, 'name' => 'Paul', 'capacity' => 15]);
        SectionSubject::query()->create(['section_id' => $historical->id, 'subject_id' => $subject->id, 'curriculum_id' => $original->id, 'schedule_text' => 'Original schedule']);
        $nextYear = AcademicYear::query()->create(['code' => '2027-2028', 'name' => 'School Year 2027-2028', 'starts_on' => '2027-06-01', 'ends_on' => '2028-04-01', 'status' => 'planned']);

        $this->actingAs($admin)->post(route('academics.admin.curricula.store'), [
            'academic_year_id' => $nextYear->id, 'name' => 'Next year', 'source_curriculum_id' => $original->id,
        ])->assertRedirect();
        $copy = Curriculum::query()->where('academic_year_id', $nextYear->id)->firstOrFail();
        $this->assertSame(1, $copy->gradeSubjects()->count());
        $this->assertSame('Paul', $copy->sectionTemplates()->firstOrFail()->name);
        $this->actingAs($admin)->post(route('academics.admin.curricula.publish', $copy))->assertRedirect();

        $this->assertSame('Original schedule', $historical->sectionSubjects()->firstOrFail()->fresh()->schedule_text);
        $this->assertSame($original->id, $historical->fresh()->curriculum_id);
        $this->assertSame(1, Section::query()->where('academic_year_id', $nextYear->id)->count());
        $this->assertSame(1, SectionSubject::query()->whereHas('section', fn ($query) => $query->where('academic_year_id', $nextYear->id))->count());
    }

    public function test_default_curriculum_remains_available_without_any_saved_versions_and_creates_the_full_program(): void
    {
        $admin = $this->user('admin');
        $this->seed(GradeLevelSeeder::class);
        $year = AcademicYear::query()->create(['code' => '2027-2028', 'name' => 'School Year 2027-2028', 'starts_on' => '2027-06-01', 'ends_on' => '2028-04-01', 'status' => 'planned']);
        $this->assertDatabaseCount('curricula', 0);

        $this->actingAs($admin)->get(route('academics.admin.curricula.index'))
            ->assertOk()
            ->assertSee('Start empty')
            ->assertSee('Default curriculum (JCA class program)')
            ->assertSee('value="'.OfficialClassProgramTemplate::SOURCE_KEY.'"', false);

        $this->actingAs($admin)->post(route('academics.admin.curricula.store'), [
            'academic_year_id' => $year->id,
            'name' => 'School curriculum 2027-2028',
            'source_curriculum_id' => OfficialClassProgramTemplate::SOURCE_KEY,
        ])->assertRedirect()->assertSessionHas('status', 'Curriculum draft created. Review it before publishing.');

        $draft = Curriculum::query()->with(['gradeSubjects', 'sectionTemplates.schedules'])->sole();
        $this->assertSame('draft', $draft->status);
        $this->assertSame($year->id, $draft->academic_year_id);
        $this->assertCount(73, $draft->gradeSubjects);
        $this->assertCount(20, $draft->sectionTemplates);
        $this->assertTrue($draft->sectionTemplates->contains('name', 'Paul'));
        $this->assertTrue($draft->sectionTemplates->contains('name', 'Jacob'));
        $this->assertTrue($draft->sectionTemplates->contains('name', 'Hope'));
        $this->assertTrue($draft->sectionTemplates->firstWhere('name', 'Paul')->schedules->contains('schedule_text', 'Monday-Friday 08:20-09:00'));
        $this->assertSame(0, Section::query()->count());
        $this->assertSame(0, TeachingAssignment::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'academics.curriculum.draft_created', 'subject_public_id' => $draft->public_id]);

        // Preschool activity programs show as a timetable like the subjects of Grade 1 and up; other notes stay text.
        $this->actingAs($admin)->get(route('academics.admin.curricula.index', ['curriculum' => $draft->public_id]))
            ->assertOk()
            ->assertSee('Hope activity program')
            ->assertSeeInOrder(['Class hours:', '11:00 – 13:00', '>Activity</th>', '>Time</th>', 'Free play', '11:00 – 11:15', 'Wrap-up', '12:55 – 13:00'], false)
            ->assertSeeInOrder(['First batch:', '08:00 – 11:00', 'Activities', '08:35 – 09:05'], false)
            ->assertDontSee('Free play 11:00; meeting 11:15')
            ->assertSee('Monday-Thursday: Reading and Math enhancement, 12:40-13:40.');

        $this->seed(OfficialClassProgram2026Seeder::class);
        $seeded = Curriculum::query()->where('academic_year_id', '!=', $year->id)->with(['gradeSubjects', 'sectionTemplates.schedules'])->sole();
        $signature = fn (Curriculum $curriculum): array => [
            'subjects' => $curriculum->gradeSubjects->map(fn ($mapping): array => [
                $mapping->grade_level_id, $mapping->subject_id, $mapping->sort_order,
            ])->sortBy(fn (array $row): string => implode(':', $row))->values()->all(),
            'sections' => $curriculum->sectionTemplates->map(fn ($template): array => [
                'grade' => $template->grade_level_id,
                'name' => $template->name,
                'capacity' => $template->capacity,
                'order' => $template->sort_order,
                'activity' => $template->activity_schedule,
                'schedules' => $template->schedules->map(fn ($schedule): array => [
                    $schedule->subject_id, $schedule->schedule_text,
                ])->sortBy(fn (array $row): int => $row[0])->values()->all(),
            ])->sortBy(fn (array $row): string => $row['grade'].':'.$row['name'])->values()->all(),
        ];
        $this->assertSame($signature($seeded), $signature($draft));
    }

    public function test_default_curriculum_validates_the_source_and_rolls_back_without_the_grade_catalog(): void
    {
        $admin = $this->user('admin');
        $registrar = $this->user('registrar');
        $teacher = $this->user('teacher');
        $year = AcademicYear::query()->create(['code' => '2027-2028', 'name' => 'School Year 2027-2028', 'starts_on' => '2027-06-01', 'ends_on' => '2028-04-01', 'status' => 'planned']);
        $payload = ['academic_year_id' => $year->id, 'name' => 'Default copy', 'source_curriculum_id' => OfficialClassProgramTemplate::SOURCE_KEY];

        $this->post(route('academics.admin.curricula.store'), $payload)->assertRedirect(route('identity.login'));
        $this->actingAs($registrar)->post(route('academics.admin.curricula.store'), $payload)->assertForbidden();
        $this->actingAs($teacher)->post(route('academics.admin.curricula.store'), $payload)->assertForbidden();
        $this->actingAs($admin)->post(route('academics.admin.curricula.store'), [...$payload, 'source_curriculum_id' => 'not-a-template'])->assertSessionHasErrors('source_curriculum_id');
        $this->actingAs($admin)->post(route('academics.admin.curricula.store'), $payload)->assertSessionHasErrors('source_curriculum_id');
        $this->assertDatabaseCount('curricula', 0);
        $this->assertDatabaseCount('curriculum_grade_subjects', 0);
        $this->assertDatabaseCount('curriculum_section_templates', 0);
    }

    public function test_unauthorized_users_and_invalid_drafts_cannot_publish(): void
    {
        $admin = $this->user('admin');
        $registrar = $this->user('registrar');
        $teacher = $this->user('teacher');
        [$year] = $this->catalog('2026-2027');
        $curriculum = Curriculum::query()->create(['academic_year_id' => $year->id, 'version' => 1, 'name' => 'Empty draft', 'status' => 'draft']);

        $this->get(route('academics.admin.curricula.index'))->assertRedirect(route('identity.login'));
        $this->actingAs($teacher)->get(route('academics.admin.curricula.index'))->assertForbidden();
        $this->actingAs($registrar)->get(route('academics.admin.curricula.index'))->assertOk();
        $this->actingAs($registrar)->post(route('academics.admin.curricula.publish', $curriculum))->assertForbidden();
        $this->actingAs($admin)->post(route('academics.admin.curricula.publish', $curriculum))->assertSessionHasErrors('curriculum');
        $this->assertSame('draft', $curriculum->fresh()->status);
        $this->assertSame(0, Section::query()->count());
    }

    public function test_admin_can_delete_an_unpublished_draft_without_touching_the_published_year(): void
    {
        $admin = $this->user('admin');
        [$year, $grade, $subject] = $this->catalog('2026-2027');
        $published = Curriculum::query()->create(['academic_year_id' => $year->id, 'version' => 1, 'name' => 'Published', 'status' => 'published', 'published_by' => $admin->id, 'published_at' => now()]);
        $section = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'curriculum_id' => $published->id, 'name' => 'Paul', 'capacity' => 15]);
        $offering = SectionSubject::query()->create(['section_id' => $section->id, 'subject_id' => $subject->id, 'curriculum_id' => $published->id]);
        $draft = Curriculum::query()->create(['academic_year_id' => $year->id, 'version' => 2, 'name' => 'Unpublished alternative', 'status' => 'draft']);
        CurriculumGradeSubject::query()->create(['curriculum_id' => $draft->id, 'grade_level_id' => $grade->id, 'subject_id' => $subject->id]);
        $template = CurriculumSectionTemplate::query()->create(['curriculum_id' => $draft->id, 'grade_level_id' => $grade->id, 'name' => 'Philip', 'capacity' => 15]);
        CurriculumTemplateSchedule::query()->create(['curriculum_section_template_id' => $template->id, 'subject_id' => $subject->id, 'schedule_text' => 'Mon-Fri 08:00-08:40']);

        $this->actingAs($admin)->get(route('academics.admin.curricula.index', ['curriculum' => $draft->public_id]))
            ->assertOk()->assertSee('Delete draft')->assertSee(route('academics.admin.curricula.destroy', $draft));

        $this->actingAs($admin)->delete(route('academics.admin.curricula.destroy', $draft), ['confirm_deletion' => '1'])
            ->assertRedirect(route('academics.admin.curricula.index'))
            ->assertSessionHas('status', 'Curriculum draft deleted. Published curricula and school-year records were kept.');

        $this->assertModelMissing($draft);
        $this->assertDatabaseMissing('curriculum_grade_subjects', ['curriculum_id' => $draft->id]);
        $this->assertDatabaseMissing('curriculum_section_templates', ['curriculum_id' => $draft->id]);
        $this->assertModelMissing($template);
        $this->assertDatabaseCount('curriculum_template_schedules', 0);
        $this->assertModelExists($published);
        $this->assertModelExists($section);
        $this->assertModelExists($offering);
        $this->assertModelExists($year);
        $this->assertModelExists($subject);
        $this->assertDatabaseHas('audit_logs', ['action' => 'academics.curriculum.draft_deleted', 'subject_public_id' => $draft->public_id, 'actor_user_id' => $admin->id]);
    }

    public function test_draft_deletion_requires_confirmation_and_admin_authorization(): void
    {
        $admin = $this->user('admin');
        $registrar = $this->user('registrar');
        $teacher = $this->user('teacher');
        [$year] = $this->catalog('2026-2027');
        $draft = Curriculum::query()->create(['academic_year_id' => $year->id, 'version' => 1, 'name' => 'Draft', 'status' => 'draft']);
        $url = route('academics.admin.curricula.destroy', $draft);

        $this->delete($url, ['confirm_deletion' => '1'])->assertRedirect(route('identity.login'));
        $this->actingAs($registrar)->get(route('academics.admin.curricula.index', ['curriculum' => $draft->public_id]))
            ->assertOk()->assertDontSee('Confirm delete draft');
        $this->actingAs($registrar)->delete($url, ['confirm_deletion' => '1'])->assertForbidden();
        $this->actingAs($teacher)->delete($url, ['confirm_deletion' => '1'])->assertForbidden();
        $this->actingAs($admin)->delete($url)->assertSessionHasErrors('confirm_deletion');
        $this->assertModelExists($draft);
    }

    public function test_published_or_applied_curriculum_cannot_be_deleted(): void
    {
        $admin = $this->user('admin');
        [$year, $grade] = $this->catalog('2026-2027');
        $published = Curriculum::query()->create(['academic_year_id' => $year->id, 'version' => 1, 'name' => 'Published', 'status' => 'published', 'published_by' => $admin->id, 'published_at' => now()]);
        $draft = Curriculum::query()->create(['academic_year_id' => $year->id, 'version' => 2, 'name' => 'Linked draft', 'status' => 'draft']);
        $section = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'curriculum_id' => $draft->id, 'name' => 'Linked section', 'capacity' => 15]);

        $this->actingAs($admin)->get(route('academics.admin.curricula.index', ['curriculum' => $published->public_id]))
            ->assertOk()->assertDontSee('Confirm delete draft');
        $this->actingAs($admin)->delete(route('academics.admin.curricula.destroy', $published), ['confirm_deletion' => '1'])->assertForbidden();
        $this->actingAs($admin)->delete(route('academics.admin.curricula.destroy', $draft), ['confirm_deletion' => '1'])->assertSessionHasErrors('curriculum');
        $this->assertModelExists($published);
        $this->assertModelExists($draft);
        $this->assertModelExists($section);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'academics.curriculum.draft_deleted']);
    }

    public function test_official_35_page_program_creates_reviewable_draft_without_changing_teachers(): void
    {
        $admin = $this->user('admin');
        $teacher = $this->user('teacher');
        $passwordHash = $teacher->password;
        $this->seed(OfficialClassProgram2026Seeder::class);

        $curriculum = Curriculum::query()->with(['gradeSubjects', 'sectionTemplates'])->firstOrFail();
        $this->assertSame('draft', $curriculum->status);
        $this->assertCount(73, $curriculum->gradeSubjects);
        $this->assertCount(20, $curriculum->sectionTemplates);
        $this->assertTrue($curriculum->sectionTemplates->contains('name', 'Isaiah'));
        $this->assertTrue($curriculum->sectionTemplates->contains('name', 'Wisdom'));
        $this->assertSame($passwordHash, $teacher->fresh()->password);
        $this->assertSame(0, Section::query()->count());
        $this->actingAs($admin)->get(route('academics.admin.curricula.index'))
            ->assertOk()->assertSee('Grade 10')->assertSee('Nursery')->assertSee('Paul')->assertSee('Publish and load Academics');

        $this->seed(OfficialClassProgram2026Seeder::class);
        $this->assertSame(1, Curriculum::query()->count());
    }

    public function test_mysql_constraints_reject_duplicate_grade_subject_mapping(): void
    {
        [$year, $grade, $subject] = $this->catalog('2026-2027');
        $curriculum = Curriculum::query()->create(['academic_year_id' => $year->id, 'version' => 1, 'name' => 'Draft', 'status' => 'draft']);
        CurriculumGradeSubject::query()->create(['curriculum_id' => $curriculum->id, 'grade_level_id' => $grade->id, 'subject_id' => $subject->id]);

        $this->expectException(QueryException::class);
        CurriculumGradeSubject::query()->create(['curriculum_id' => $curriculum->id, 'grade_level_id' => $grade->id, 'subject_id' => $subject->id]);
    }

    public function test_teacher_schedule_conflict_can_be_resolved_by_editing_a_section_time(): void
    {
        $admin = $this->user('admin');
        $teacher = $this->user('teacher');
        $staff = StaffProfile::query()->create(['user_id' => $teacher->id, 'active_from' => now()->subDay()]);
        [$year, $grade, $subject] = $this->catalog('2026-2027');
        $first = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Paul', 'capacity' => 15]);
        $second = Section::query()->create(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'Philip', 'capacity' => 15]);
        $firstSubject = SectionSubject::query()->create(['section_id' => $first->id, 'subject_id' => $subject->id, 'schedule_text' => 'Monday-Friday 08:20-09:00']);
        $secondSubject = SectionSubject::query()->create(['section_id' => $second->id, 'subject_id' => $subject->id, 'schedule_text' => 'Monday-Friday 08:40-09:20']);

        $assignment = fn (Section $section, SectionSubject $sectionSubject): array => [
            'staff_profile_id' => $staff->id, 'section_id' => $section->id,
            'section_subject_id' => $sectionSubject->id, 'assignment_type' => 'subject_teacher', 'is_active' => true,
        ];
        $this->actingAs($admin)->post(route('academics.admin.teaching-assignments.store'), $assignment($first, $firstSubject))->assertRedirect();
        $this->actingAs($admin)->post(route('academics.admin.teaching-assignments.store'), $assignment($second, $secondSubject))->assertSessionHasErrors('schedule_text');
        $this->actingAs($admin)->patch(route('academics.admin.section-subjects.update', $secondSubject), [
            'schedule_text' => 'Monday-Friday 09:00-09:40',
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('academics.admin.teaching-assignments.store'), $assignment($second, $secondSubject))->assertRedirect();
        $this->assertSame(2, TeachingAssignment::query()->where('staff_profile_id', $staff->id)->count());
    }

    /** @return array{AcademicYear, GradeLevel, Subject} */
    private function catalog(string $yearCode): array
    {
        return [
            AcademicYear::query()->create(['code' => $yearCode, 'name' => 'School Year '.$yearCode, 'starts_on' => '2026-06-08', 'ends_on' => '2027-04-08', 'status' => 'planned']),
            GradeLevel::query()->create(['code' => 'G1', 'name' => 'Grade 1', 'sequence' => 4, 'school_stage' => 'Elementary', 'is_active' => true]),
            Subject::query()->create(['code' => 'GMRC', 'name' => 'Good Manners and Right Conduct', 'is_active' => true]),
        ];
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
