<?php

namespace App\Modules\Academics\Queries;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Academics\Support\CurrentAcademicYear;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use App\Modules\StudentSupport\Queries\TeacherSupportIndicatorQuery;
use Illuminate\Support\Collection;

/**
 * Builds the teacher's "My teaching" page for one school year at a time: one entry per
 * section (adviser and subject roles merged), its class schedule with each subject's
 * teacher, and a single alphabetical student list.
 */
class TeacherWorkspaceQuery
{
    public function __construct(
        private readonly TeacherAssignmentsQuery $assignments,
        private readonly CurrentAcademicYear $currentYear,
        private readonly TeacherSupportIndicatorQuery $supportIndicator,
    ) {}

    /**
     * @return array{years: Collection<int, AcademicYear>, year: ?AcademicYear, sections: Collection<int, array<string, mixed>>, totals: array{sections: int, classes: int, students: int}}
     */
    public function forUser(User $user, ?string $yearPublicId): array
    {
        $records = $this->assignments->forUser($user);
        $years = self::yearsOf($records);
        $year = $this->currentYear->resolve($years, $yearPublicId);
        $inYear = $year ? $records->filter(fn (TeachingAssignment $assignment): bool => $assignment->section->academic_year_id === $year->id) : collect();

        $placements = SectionAssignment::query()
            ->whereIn('section_id', $inYear->pluck('section_id')->unique())
            ->where('status', 'confirmed')->whereNull('ended_at')
            ->whereHas('enrollment', fn ($query) => $query->where('status', 'enrolled'))
            ->with('enrollment.student')
            ->get();
        $withSupport = $this->supportIndicator->enrollmentsWithSummary($placements->pluck('enrollment_id'))->flip();

        $sections = $inYear->groupBy('section_id')->map(function (Collection $roles) use ($placements, $withSupport): array {
            $section = $roles->first()->section;
            $subjectRoles = $roles->where('assignment_type', 'subject_teacher')->filter(fn (TeachingAssignment $role): bool => $role->sectionSubject !== null);
            $teachers = $section->teachingAssignments
                ->where('assignment_type', 'subject_teacher')
                ->groupBy('section_subject_id')
                ->map(fn (Collection $group) => $group->map(fn (TeachingAssignment $role) => $role->staffProfile?->user?->name)->filter()->unique()->join(', '));
            $students = $placements->where('section_id', $section->id)
                ->unique('enrollment.student_id')
                ->sortBy(fn (SectionAssignment $placement): string => mb_strtolower($placement->enrollment->student->last_name.' '.$placement->enrollment->student->first_name))
                ->values()
                ->map(fn (SectionAssignment $placement): array => [
                    'student' => $placement->enrollment->student,
                    'hasSupport' => $withSupport->has($placement->enrollment_id),
                ]);

            return [
                'section' => $section,
                'isAdviser' => $roles->contains('assignment_type', 'adviser'),
                'subjectRoles' => $subjectRoles->sortBy(fn (TeachingAssignment $role): string => $role->sectionSubject->subject->name)->values(),
                'yourOfferingIds' => $subjectRoles->pluck('section_subject_id')->all(),
                'teachers' => $teachers,
                'students' => $students,
            ];
        })->sortBy(fn (array $entry): string => sprintf('%05d-%05d-%s', $entry['section']->gradeLevel->sequence, $entry['section']->sort_order ?? 0, $entry['section']->name))->values();

        return [
            'years' => $years,
            'year' => $year,
            'sections' => $sections,
            'totals' => [
                'sections' => $sections->count(),
                'classes' => $sections->sum(fn (array $entry): int => $entry['subjectRoles']->count()),
                'students' => $sections->sum(fn (array $entry): int => $entry['students']->count()),
            ],
        ];
    }

    /**
     * School years the teacher has active assignments in, newest first.
     *
     * @param  Collection<int, TeachingAssignment>  $records
     * @return Collection<int, AcademicYear>
     */
    public static function yearsOf(Collection $records): Collection
    {
        return $records->map(fn (TeachingAssignment $assignment) => $assignment->section->academicYear)
            ->unique('id')
            ->sortByDesc(fn (AcademicYear $year): string => $year->starts_on->format('Y-m-d').'-'.str_pad((string) $year->id, 10, '0', STR_PAD_LEFT))
            ->values();
    }
}
