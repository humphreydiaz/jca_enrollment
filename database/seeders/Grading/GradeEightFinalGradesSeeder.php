<?php

namespace Database\Seeders\Grading;

use App\Models\User;
use App\Modules\Academics\Actions\CreateTeachingAssignmentAction;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Grading\Actions\FinalizeGradeAction;
use App\Modules\Grading\Actions\SaveQuarterGradesAction;
use App\Modules\Grading\Actions\SubmitGradeAction;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Models\StaffProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Demonstration teachers and approved final grades for every Grade 8 section of the open 2026-2027 year,
 * through the same actions the screens use (D-058):
 *
 * - the Admin assigns a subject teacher to each offering that has none (subjects in pairs, the least-loaded
 *   free teacher first, never a schedule conflict) and an adviser to a section that has none;
 * - each subject teacher saves all four quarter grades for the enrolled learners and submits them;
 * - the Registrar approves them, so families and learners see them under Final grades.
 *
 * Quarters 2-4 have not happened yet in the real school year: these are demonstration grades recorded now.
 * Existing assignments and grades are kept (a finalized grade is never changed), so a rerun adds nothing.
 *
 * php artisan db:seed --class="Database\Seeders\Grading\GradeEightFinalGradesSeeder"
 */
class GradeEightFinalGradesSeeder extends Seeder
{
    private const YEAR_CODE = '2026-2027';

    /** Subjects one teacher usually handles together. */
    private const SUBJECT_PAIRS = [['ENG', 'FIL'], ['MATH', 'SCI'], ['AP', 'ESP'], ['TLE', 'MAPEH']];

    private User $admin;

    private User $registrar;

    public function run(): void
    {
        $year = AcademicYear::query()->where('code', self::YEAR_CODE)->where('status', 'open')->first()
            ?? throw new RuntimeException('An open '.self::YEAR_CODE.' school year is required.');
        $sections = Section::query()->where('academic_year_id', $year->id)->where('status', 'active')
            ->whereHas('gradeLevel', fn ($query) => $query->where('code', 'G8'))->orderBy('name')->get();
        if ($sections->isEmpty()) {
            throw new RuntimeException('No active Grade 8 section exists in '.self::YEAR_CODE.'.');
        }
        $this->admin = $this->staffWithRole('admin');
        $this->registrar = $this->staffWithRole('registrar', fallback: $this->admin);

        $rows = DB::transaction(function () use ($sections, $year): array {
            $rows = [];
            foreach ($sections as $section) {
                $this->assignTeachers($section, $year);
                $rows = [...$rows, ...$this->gradeSection($section)];
            }

            return $rows;
        });

        $this->command?->table(['Section', 'Learner', 'Subjects with approved final grades', 'General average'], $rows);
        $this->command?->warn('Quarters 2-4 are demonstration grades: the real quarters have not happened yet.');
    }

    private function staffWithRole(string $role, ?User $fallback = null): User
    {
        return User::query()->where('status', AccountStatus::Active)
            ->whereHas('roles', fn ($query) => $query->where('code', $role))->orderBy('id')->first()
            ?? $fallback
            ?? throw new RuntimeException("An active {$role} account is required.");
    }

    // ---------------------------------------------------------------- teachers (Admin)

    private function assignTeachers(Section $section, AcademicYear $year): void
    {
        $create = app(CreateTeachingAssignmentAction::class);
        $offerings = SectionSubject::query()->with('subject')->where('section_id', $section->id)->where('status', 'active')
            ->whereDoesntHave('teachingAssignments', fn ($query) => $query->where('is_active', true)->where('assignment_type', 'subject_teacher'))
            ->get()->toBase() // a plain collection: only()/except() below pick by subject code, not model ID
            ->keyBy(fn (SectionSubject $offering) => $offering->subject->code);

        $groups = collect(self::SUBJECT_PAIRS)->map(fn (array $pair) => $offerings->only($pair)->values())->filter->isNotEmpty()->values();
        $paired = collect(self::SUBJECT_PAIRS)->flatten()->all();
        foreach ($offerings->except($paired) as $offering) {
            $groups->push(collect([$offering]));
        }

        $homeroom = null;
        foreach ($groups as $group) {
            foreach ($this->candidatesFor($group, $year) as $teacher) {
                try {
                    DB::transaction(function () use ($create, $group, $section, $teacher, $year): void {
                        foreach ($group as $offering) {
                            $create->execute([
                                'staff_profile_id' => $teacher->id, 'section_id' => $section->id, 'section_subject_id' => $offering->id,
                                'assignment_type' => 'subject_teacher', 'active_from' => $year->starts_on->toDateString(), 'is_active' => true,
                            ], $this->admin);
                        }
                    });
                    $homeroom ??= $teacher;
                    break;
                } catch (ValidationException) {
                    continue; // busy at that time: try the next teacher
                }
            }
        }

        $hasAdviser = TeachingAssignment::query()->where('section_id', $section->id)->where('assignment_type', 'adviser')->where('is_active', true)->exists();
        $homeroom ??= $this->teachersByLoad($year)->first();
        if (! $hasAdviser && $homeroom !== null) {
            $create->execute([
                'staff_profile_id' => $homeroom->id, 'section_id' => $section->id, 'section_subject_id' => null,
                'assignment_type' => 'adviser', 'active_from' => $year->starts_on->toDateString(), 'is_active' => true,
            ], $this->admin);
        }
    }

    /**
     * Whoever already teaches these subjects in Grade 8 this year first (one teacher per subject across the grade),
     * then everyone else by load.
     *
     * @param  Collection<int, SectionSubject>  $group
     * @return Collection<int, StaffProfile>
     */
    private function candidatesFor(Collection $group, AcademicYear $year): Collection
    {
        $current = TeachingAssignment::query()->where('is_active', true)->where('assignment_type', 'subject_teacher')
            ->whereHas('section', fn ($sections) => $sections->where('academic_year_id', $year->id)->whereHas('gradeLevel', fn ($grade) => $grade->where('code', 'G8')))
            ->whereHas('sectionSubject', fn ($offering) => $offering->whereIn('subject_id', $group->pluck('subject_id')))
            ->pluck('staff_profile_id')->unique();
        [$preferred, $others] = $this->teachersByLoad($year)->partition(fn (StaffProfile $teacher) => $current->contains($teacher->id));

        return $preferred->concat($others)->values();
    }

    /**
     * Active teachers, fewest subject classes this year first. Preschool advisers are left out: their batch hours
     * are not recorded as subject classes, so the schedule check could not protect them.
     *
     * @return Collection<int, StaffProfile>
     */
    private function teachersByLoad(AcademicYear $year): Collection
    {
        $preschoolAdvisers = TeachingAssignment::query()->where('is_active', true)->where('assignment_type', 'adviser')
            ->whereHas('section', fn ($sections) => $sections->where('academic_year_id', $year->id)->whereHas('gradeLevel', fn ($grade) => $grade->whereIn('code', ['NUR', 'JK', 'SK'])))
            ->pluck('staff_profile_id');
        $load = TeachingAssignment::query()->where('is_active', true)->where('assignment_type', 'subject_teacher')
            ->whereHas('section', fn ($sections) => $sections->where('academic_year_id', $year->id))
            ->selectRaw('staff_profile_id, COUNT(*) as classes')->groupBy('staff_profile_id')->pluck('classes', 'staff_profile_id');

        return StaffProfile::query()
            ->whereHas('user', fn ($query) => $query->where('status', AccountStatus::Active)->whereHas('roles', fn ($roles) => $roles->where('code', 'teacher')))
            ->whereNotIn('id', $preschoolAdvisers)
            ->get()->sortBy([fn (StaffProfile $a, StaffProfile $b) => [(int) ($load[$a->id] ?? 0), $a->id] <=> [(int) ($load[$b->id] ?? 0), $b->id]])->values();
    }

    // ---------------------------------------------------------------- grades (teachers, then the Registrar)

    /** @return list<array{0: string, 1: string, 2: int, 3: string}> */
    private function gradeSection(Section $section): array
    {
        $enrollments = Enrollment::query()->with('student')->where('status', 'enrolled')
            ->whereHas('sectionAssignments', fn ($query) => $query->where('section_id', $section->id)->where('status', 'confirmed')->whereNull('ended_at'))
            ->get()->sortBy(fn (Enrollment $enrollment) => $enrollment->student->last_name.' '.$enrollment->student->first_name);
        $assignments = TeachingAssignment::query()->with(['sectionSubject.subject', 'staffProfile.user'])
            ->where('section_id', $section->id)->where('assignment_type', 'subject_teacher')->where('is_active', true)
            ->whereHas('sectionSubject', fn ($query) => $query->where('status', 'active'))->get()
            ->unique('section_subject_id');

        $rows = [];
        foreach ($enrollments as $enrollment) {
            foreach ($assignments as $assignment) {
                $this->gradeOne($assignment, $enrollment);
            }
            $finals = StudentSubjectGrade::query()->where('enrollment_id', $enrollment->id)->where('status', 'finalized')->pluck('final_grade');
            $rows[] = [$section->name, $enrollment->student->full_name, $finals->count(), $finals->isEmpty() ? '—' : number_format($finals->avg(), 2)];
        }

        return $rows;
    }

    private function gradeOne(TeachingAssignment $assignment, Enrollment $enrollment): void
    {
        $teacher = $assignment->staffProfile->user;
        $subjectId = $assignment->sectionSubject->subject_id;
        $grade = StudentSubjectGrade::query()->where('enrollment_id', $enrollment->id)->where('subject_id', $subjectId)->first();
        if ($grade?->status === 'finalized') {
            return;
        }

        if ($grade === null || $grade->status === 'draft') {
            $quarters = [];
            foreach ($this->quarterGrades($enrollment->student->student_number.'|'.$assignment->sectionSubject->subject->code) as $quarter => $value) {
                $quarters["quarter_{$quarter}"] = $grade?->{"quarter_{$quarter}"} ?? $value; // keep what the teacher already entered
            }
            $grade = app(SaveQuarterGradesAction::class)->execute($assignment, $enrollment, $teacher, $quarters);
            app(SubmitGradeAction::class)->execute($grade, $assignment, $teacher);
        }

        app(FinalizeGradeAction::class)->execute($grade->fresh(), $this->registrar);
    }

    /**
     * Believable, repeatable quarter grades: a learner-and-subject level of 80-93 that drifts by a point or two.
     *
     * @return array<int, int>
     */
    private function quarterGrades(string $seed): array
    {
        $level = 80 + crc32($seed) % 14;
        $grades = [];
        foreach ([1, 2, 3, 4] as $quarter) {
            $drift = (int) (crc32($seed.'|q'.$quarter) % 5) - 2;
            $grades[$quarter] = max(75, min(98, $level + $drift + ($quarter >= 3 ? 1 : 0)));
        }

        return $grades;
    }
}
