<?php

namespace App\Modules\Grading\Queries;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Academics\Support\CurrentAcademicYear;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Grading\Support\GradeReviewFlags;
use App\Modules\Grading\Support\NumericGradeLevels;
use App\Modules\People\Models\StudentProfile;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Grade approval board for the Registrar/Admin: every Grades 1-10 class (section ×
 * subject) of one school year with how far its current roster has got — approved,
 * waiting for approval, reopen requested, or still with the teacher. There is no row
 * cap: classes are counted from rosters and grades, then paginated by the page.
 */
class GradeApprovalBoardQuery
{
    /** Order also used for the progress bar. */
    public const STAGES = ['approved', 'submitted', 'correction', 'ready', 'in_progress', 'not_started'];

    public function __construct(private readonly CurrentAcademicYear $currentYear) {}

    /**
     * Resolve filter models and dropdown options from validated input.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function filters(array $input): array
    {
        $years = AcademicYear::query()->orderByDesc('starts_on')->get();
        $year = $this->currentYear->resolve($years, $input['year'] ?? null);
        $grades = GradeLevel::query()->whereIn('code', NumericGradeLevels::codes())->orderBy('sequence')->get();
        $sections = $year ? Section::query()->where('academic_year_id', $year->id)->whereIn('grade_level_id', $grades->pluck('id'))
            ->with('gradeLevel')->get()
            ->sortBy(fn (Section $section): string => sprintf('%05d-%05d-%s', $section->gradeLevel->sequence, $section->sort_order ?? 0, $section->name))->values() : collect();
        $offerings = SectionSubject::query()->whereIn('section_id', $sections->pluck('id'))->active();
        $subjects = Subject::query()->whereIn('id', (clone $offerings)->select('subject_id'))->orderBy('name')->get();
        $teachingStaff = TeachingAssignment::query()->active()->where('assignment_type', 'subject_teacher')
            ->whereIn('section_id', $sections->pluck('id'))->select('staff_profile_id');
        $teachers = User::query()->whereHas('staffProfile', fn (Builder $staff) => $staff->whereIn('id', $teachingStaff))
            ->orderBy('name')->get();

        $grade = $grades->firstWhere('public_id', $input['grade'] ?? null);
        $section = $sections->firstWhere('public_id', $input['section'] ?? null);

        return [
            'years' => $years, 'year' => $year,
            'grades' => $grades, 'grade' => $grade,
            'sections' => $sections, 'section' => $grade && $section && $section->grade_level_id !== $grade->id ? null : $section,
            'subjects' => $subjects, 'subject' => $subjects->firstWhere('public_id', $input['subject'] ?? null),
            'teachers' => $teachers, 'teacher' => $teachers->firstWhere('public_id', $input['teacher'] ?? null),
            'search' => filled($input['search'] ?? null) ? trim((string) $input['search']) : null,
            'flagged' => (bool) ($input['flagged'] ?? false),
        ];
    }

    /**
     * Every class matching the filters, with progress counts.
     *
     * @param  array<string, mixed>  $f  output of filters()
     * @return Collection<int, array<string, mixed>>
     */
    public function classes(array $f): Collection
    {
        if ($f['year'] === null) {
            return collect();
        }

        $sections = Section::query()->where('academic_year_id', $f['year']->id)
            ->whereHas('gradeLevel', fn (Builder $grade) => $grade->whereIn('code', NumericGradeLevels::codes()))
            ->when($f['grade'], fn (Builder $query) => $query->where('grade_level_id', $f['grade']->id))
            ->when($f['section'], fn (Builder $query) => $query->whereKey($f['section']->id))
            ->with([
                'gradeLevel',
                'sectionSubjects' => fn ($query) => $query->when($f['subject'], fn ($q) => $q->where('subject_id', $f['subject']->id))->with('subject'),
                'teachingAssignments' => fn ($query) => $query->active()->where('assignment_type', 'subject_teacher')->with('staffProfile.user'),
            ])
            ->get();

        $placements = $this->placements($sections->pluck('id'));
        if ($f['search'] !== null) {
            $needle = mb_strtolower($f['search']);
            $matching = $placements->filter(fn (SectionAssignment $placement): bool => str_contains(mb_strtolower($placement->enrollment->student->full_name), $needle)
                || str_contains(mb_strtolower((string) $placement->enrollment->student->student_number), $needle));
            $sections = $sections->whereIn('id', $matching->pluck('section_id')->unique());
        }
        $grades = $this->grades($placements->pluck('enrollment_id'), $sections->flatMap->sectionSubjects->pluck('subject_id')->unique());
        $students = $placements->mapWithKeys(fn (SectionAssignment $placement): array => [$placement->enrollment_id => $placement->enrollment->student]);

        return $sections->flatMap(function (Section $section) use ($placements, $grades, $students): Collection {
            $roster = $placements->where('section_id', $section->id)->pluck('enrollment_id')->unique()->values();

            return $section->sectionSubjects->map(fn (SectionSubject $offering): array => $this->summarise($section, $offering, $roster, $grades, $students));
        })
            ->filter(fn (array $class): bool => $class['total'] > 0)
            ->when($f['teacher'], fn (Collection $classes) => $classes->filter(fn (array $class): bool => $class['teachers']->contains('id', $f['teacher']->id)))
            ->when($f['flagged'], fn (Collection $classes) => $classes->filter(fn (array $class): bool => $class['flagged'] > 0))
            ->sortBy(fn (array $class): string => sprintf('%05d-%05d-%s-%s', $class['grade']->sequence, $class['section']->sort_order ?? 0, $class['section']->name, $class['subject']->name))
            ->values();
    }

    /**
     * One class with every current student's grade, for the review dialog.
     *
     * @return array<string, mixed>|null
     */
    public function classDetail(Section $section, Subject $subject): ?array
    {
        $section->loadMissing(['gradeLevel', 'academicYear', 'teachingAssignments' => fn ($query) => $query->active()->where('assignment_type', 'subject_teacher')->with('staffProfile.user')]);
        $offering = SectionSubject::query()->where('section_id', $section->id)->where('subject_id', $subject->id)->active()->with('subject')->first();
        if ($offering === null || ! NumericGradeLevels::includes($section->gradeLevel)) {
            return null;
        }

        $placements = $this->placements(collect([$section->id]))
            ->sortBy(fn (SectionAssignment $placement): string => mb_strtolower($placement->enrollment->student->last_name.' '.$placement->enrollment->student->first_name))
            ->unique('enrollment_id')->values();
        $grades = $this->grades($placements->pluck('enrollment_id'), collect([$subject->id]));
        $students = $placements->mapWithKeys(fn (SectionAssignment $placement): array => [$placement->enrollment_id => $placement->enrollment->student]);
        $summary = $this->summarise($section, $offering, $placements->pluck('enrollment_id'), $grades, $students);

        $summary['rows'] = $placements->map(function (SectionAssignment $placement) use ($grades, $subject): array {
            $grade = $grades->get($placement->enrollment_id.':'.$subject->id);

            return [
                'student' => $placement->enrollment->student,
                'grade' => $grade,
                'stage' => self::stage($grade),
                'flags' => $grade && self::stage($grade) === 'submitted' ? GradeReviewFlags::for($grade) : [],
            ];
        });
        $summary['approvable'] = $summary['rows']->where('stage', 'submitted')->pluck('grade')->values();

        return $summary;
    }

    /**
     * Applies the board filters to a StudentSubjectGrade query (Reopen and Approved tabs).
     *
     * @param  Builder<StudentSubjectGrade>  $query
     * @param  array<string, mixed>  $f  output of filters()
     */
    public function scopeGrades(Builder $query, array $f): void
    {
        $query->whereHas('enrollment', function (Builder $enrollment) use ($f): void {
            $enrollment->when($f['year'], fn (Builder $q) => $q->where('academic_year_id', $f['year']->id))
                ->when($f['grade'], fn (Builder $q) => $q->where('grade_level_id', $f['grade']->id))
                ->when($f['search'], fn (Builder $q) => $q->whereHas('student', fn (Builder $student) => $student->where(fn (Builder $match) => $match
                    ->where('last_name', 'like', '%'.$f['search'].'%')
                    ->orWhere('first_name', 'like', '%'.$f['search'].'%')
                    ->orWhere('student_number', 'like', '%'.$f['search'].'%'))));
        })
            ->when($f['subject'], fn (Builder $q) => $q->where('subject_id', $f['subject']->id))
            ->when($f['section'], fn (Builder $q) => $q->whereHas('sectionSubject', fn (Builder $offering) => $offering->where('section_id', $f['section']->id)))
            ->when($f['teacher'], fn (Builder $q) => $q->whereHas('teachingAssignment.staffProfile', fn (Builder $staff) => $staff->where('user_id', $f['teacher']->id)));
    }

    public static function stage(?StudentSubjectGrade $grade): string
    {
        if ($grade === null) {
            return 'not_started';
        }
        if ($grade->status === 'finalized' && ! $grade->has_pending_correction) {
            return 'approved';
        }
        if ($grade->has_pending_correction) {
            return 'correction';
        }
        if ($grade->status === 'submitted') {
            return 'submitted';
        }
        if ($grade->isComplete()) {
            return 'ready';
        }

        return collect([$grade->quarter_1, $grade->quarter_2, $grade->quarter_3, $grade->quarter_4])->contains(fn ($value): bool => $value !== null)
            ? 'in_progress' : 'not_started';
    }

    /**
     * @param  Collection<int, int>  $roster  enrollment ids
     * @param  Collection<string, StudentSubjectGrade>  $grades  keyed "enrollment:subject"
     * @param  Collection<int, StudentProfile>  $students  keyed by enrollment id
     * @return array<string, mixed>
     */
    private function summarise(Section $section, SectionSubject $offering, Collection $roster, Collection $grades, Collection $students): array
    {
        $counts = array_fill_keys(self::STAGES, 0);
        $submitted = collect();
        foreach ($roster as $enrollmentId) {
            $grade = $grades->get($enrollmentId.':'.$offering->subject_id);
            $stage = self::stage($grade);
            $counts[$stage]++;
            if ($stage === 'submitted') {
                $submitted->push($grade);
            }
        }
        $teachers = $section->teachingAssignments->where('section_subject_id', $offering->id)
            ->map(fn (TeachingAssignment $assignment) => $assignment->staffProfile?->user)->filter()->unique('id')->values();
        $total = $roster->count();
        $remaining = $counts['ready'] + $counts['in_progress'] + $counts['not_started'];

        return [
            'key' => $section->public_id.':'.$offering->subject->public_id,
            'section' => $section,
            'grade' => $section->gradeLevel,
            'subject' => $offering->subject,
            'teachers' => $teachers,
            'teacherNames' => $teachers->pluck('name')->join(', ') ?: 'No teacher assigned',
            'total' => $total,
            'counts' => $counts,
            'remaining' => $remaining,
            'state' => match (true) {
                $total === 0 => 'empty',
                $counts['submitted'] + $counts['correction'] > 0 => 'waiting',
                $counts['approved'] === $total => 'approved',
                $counts['not_started'] === $total => 'not_started',
                default => 'in_progress',
            },
            'oldestSubmittedAt' => $submitted->min('submitted_at'),
            'flagged' => $submitted->filter(fn (StudentSubjectGrade $grade): bool => GradeReviewFlags::for($grade) !== [])->count(),
            'range' => $submitted->isEmpty() ? null : [$submitted->min('final_grade'), $submitted->max('final_grade')],
            // Every waiting grade with its student, for the at-a-glance strip and "Review all" (D-045).
            'waiting' => $submitted
                ->map(fn (StudentSubjectGrade $grade): array => ['grade' => $grade, 'student' => $students->get($grade->enrollment_id), 'flags' => GradeReviewFlags::for($grade)])
                ->sortBy(fn (array $row): string => mb_strtolower(($row['student']?->last_name ?? '').' '.($row['student']?->first_name ?? '')))
                ->values(),
        ];
    }

    /**
     * @param  Collection<int, int>  $sectionIds
     * @return Collection<int, SectionAssignment>
     */
    private function placements(Collection $sectionIds): Collection
    {
        return SectionAssignment::query()->whereIn('section_id', $sectionIds)
            ->where('status', 'confirmed')->whereNull('ended_at')
            ->whereHas('enrollment', fn (Builder $query) => $query->where('status', 'enrolled'))
            ->with('enrollment.student')
            ->get();
    }

    /**
     * @param  Collection<int, int>  $enrollmentIds
     * @param  Collection<int, int>  $subjectIds
     * @return Collection<string, StudentSubjectGrade>
     */
    private function grades(Collection $enrollmentIds, Collection $subjectIds): Collection
    {
        if ($enrollmentIds->isEmpty() || $subjectIds->isEmpty()) {
            return collect();
        }

        return StudentSubjectGrade::query()
            ->whereIn('enrollment_id', $enrollmentIds->unique())
            ->whereIn('subject_id', $subjectIds->unique())
            ->withExists(['corrections as has_pending_correction' => fn (Builder $query) => $query->where('status', 'pending')])
            ->get()
            ->keyBy(fn (StudentSubjectGrade $grade): string => $grade->enrollment_id.':'.$grade->subject_id);
    }
}
