<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Academics\Actions\CreateCurriculumDraftAction;
use App\Modules\Academics\Actions\PublishCurriculumAction;
use App\Modules\Academics\Actions\SaveAcademicCatalogAction;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StaffProfile;
use App\Modules\People\Models\StudentProfile;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use Database\Seeders\Academics\GradeLevelSeeder;
use Database\Seeders\Grading\GradeEightFinalGradesSeeder;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GradeEightFinalGradesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_staffs_grade_8_and_releases_approved_final_grades_to_the_learner(): void
    {
        [$section, $student, $nurseryAdviser] = $this->gradeEightLearner();
        $offerings = SectionSubject::query()->where('section_id', $section->id)->where('status', 'active')->count();
        $this->assertGreaterThan(0, $offerings);

        $this->seed(GradeEightFinalGradesSeeder::class);

        // Every subject has one teacher, the section has an adviser, and nobody is double-booked.
        $this->assertSame($offerings, TeachingAssignment::query()->where('section_id', $section->id)->where('assignment_type', 'subject_teacher')->where('is_active', true)->count());
        $this->assertTrue(TeachingAssignment::query()->where('section_id', $section->id)->where('assignment_type', 'adviser')->where('is_active', true)->exists());
        $this->assertFalse(TeachingAssignment::query()->where('staff_profile_id', $nurseryAdviser->id)->where('assignment_type', 'subject_teacher')->exists());
        $teacherOf = fn (string $code) => TeachingAssignment::query()->where('section_id', $section->id)->where('assignment_type', 'subject_teacher')
            ->whereHas('sectionSubject.subject', fn ($query) => $query->where('code', $code))->value('staff_profile_id');
        $this->assertSame($teacherOf('ENG'), $teacherOf('FIL'));
        $this->assertSame($teacherOf('MATH'), $teacherOf('SCI'));
        $this->assertNotSame($teacherOf('ENG'), $teacherOf('MATH'));

        // Each subject: four quarters by its teacher, submitted, then approved by the Registrar.
        $grades = StudentSubjectGrade::query()->where('enrollment_id', $student['enrollment']->id)->get();
        $this->assertCount($offerings, $grades);
        foreach ($grades as $grade) {
            $this->assertSame('finalized', $grade->status);
            $this->assertSame(intdiv($grade->quarter_1 + $grade->quarter_2 + $grade->quarter_3 + $grade->quarter_4 + 2, 4), $grade->final_grade);
            $this->assertTrue($grade->final_grade >= 75 && $grade->final_grade <= 98);
            $this->assertTrue(User::query()->findOrFail($grade->finalized_by)->hasRole('registrar'));
            $this->assertTrue(User::query()->findOrFail($grade->submitted_by)->hasRole('teacher'));
        }

        // The learner sees the released grades.
        $first = $grades->sortBy('subject_name_snapshot')->first();
        $this->actingAs($student['user'])->get(route('grading.family.index'))
            ->assertOk()
            ->assertSee($first->subject_name_snapshot)
            ->assertSee((string) $first->final_grade)
            ->assertDontSee('No finalized grades are available');

        // A rerun keeps everything as it is.
        $before = [TeachingAssignment::query()->count(), StudentSubjectGrade::query()->count(), DB::table('grade_revisions')->count()];
        $this->seed(GradeEightFinalGradesSeeder::class);
        $this->assertSame($before, [TeachingAssignment::query()->count(), StudentSubjectGrade::query()->count(), DB::table('grade_revisions')->count()]);
    }

    /** @return array{0: Section, 1: array{enrollment: Enrollment, user: User}, 2: StaffProfile} */
    private function gradeEightLearner(): array
    {
        $this->seed([RoleSeeder::class, GradeLevelSeeder::class]);
        $admin = $this->account('Ana Admin', 'admin');
        $this->account('Rita Registrar', 'registrar');
        foreach (['Tomas Uno', 'Teresa Dos', 'Tito Tres', 'Tala Cuatro', 'Tino Cinco', 'Tess Seis'] as $name) {
            StaffProfile::query()->create(['user_id' => $this->account($name, 'teacher')->id, 'active_from' => '2026-06-08']);
        }

        $year = app(SaveAcademicCatalogAction::class)->execute(new AcademicYear, [
            'code' => '2026-2027', 'name' => 'School Year 2026-2027', 'starts_on' => '2026-06-08', 'ends_on' => '2027-04-08',
            'enrollment_opens_on' => '2026-05-04', 'enrollment_closes_on' => '2026-08-20', 'status' => 'planned',
        ], $admin, 'academics.academic_year.created');
        $curriculum = app(CreateCurriculumDraftAction::class)->execute($year, 'JCA 2026-2027 class program', null, $admin, useOfficialDefault: true);
        app(PublishCurriculumAction::class)->execute($curriculum, $admin);
        app(SaveAcademicCatalogAction::class)->execute($year, ['status' => 'open'], $admin, 'academics.academic_year.updated');

        // A preschool adviser, whose batch hours the schedule check cannot see, is never given Grade 8 subjects.
        $nurseryAdviser = StaffProfile::query()->create(['user_id' => $this->account('Nora Nursery', 'teacher')->id, 'active_from' => '2026-06-08']);
        $nursery = Section::query()->where('academic_year_id', $year->id)->whereHas('gradeLevel', fn ($query) => $query->where('code', 'NUR'))->firstOrFail();
        TeachingAssignment::query()->create(['staff_profile_id' => $nurseryAdviser->id, 'section_id' => $nursery->id, 'assignment_type' => 'adviser', 'is_active' => true]);

        $section = Section::query()->where('academic_year_id', $year->id)->whereHas('gradeLevel', fn ($query) => $query->where('code', 'G8'))->orderBy('name')->firstOrFail();
        $user = $this->account('Junemar Aguilar Fajardo', 'student');
        $profile = StudentProfile::query()->create(['user_id' => $user->id, 'first_name' => 'Junemar', 'middle_name' => 'Aguilar', 'last_name' => 'Fajardo', 'student_number' => 'JCA-26-158']);
        $guardian = GuardianProfile::query()->create(['first_name' => 'Fictional', 'last_name' => 'Fajardo']);
        $enrollment = Enrollment::query()->create([
            'application_number' => 'JCA-2026-GRADE8AA', 'student_id' => $profile->id, 'academic_year_id' => $year->id, 'grade_level_id' => $section->grade_level_id,
            'applicant_guardian_id' => $guardian->id, 'student_category' => 'regular', 'status' => 'enrolled', 'active_enrollment_key' => $profile->id.':'.$year->id, 'enrolled_at' => now(),
        ]);
        SectionAssignment::query()->create([
            'enrollment_id' => $enrollment->id, 'section_id' => $section->id, 'status' => 'confirmed', 'active_assignment_key' => (string) $enrollment->id,
            'confirmed_by' => $admin->id, 'confirmed_at' => now(), 'effective_from' => '2026-06-08',
        ]);

        return [$section, ['enrollment' => $enrollment, 'user' => $user], $nurseryAdviser];
    }

    private function account(string $name, string $role): User
    {
        $user = User::query()->create(['name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)).'@example.test', 'password' => 'Password12345', 'status' => 'active']);
        app(AssignRolesAction::class)->execute($user, [$role], null);

        return $user;
    }
}
