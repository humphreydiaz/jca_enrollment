<?php

namespace App\Modules\Academics\Queries;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\Curriculum;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Support\AcademicRecordUsage;
use App\Modules\Academics\Support\CurrentAcademicYear;
use App\Modules\People\Models\StaffProfile;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read model for the Academics screens. Everything is scoped to one school year so the Admin
 * never has to scroll through every year's sections at once.
 */
class AcademicWorkspaceQuery
{
    public function __construct(
        private readonly CurrentAcademicYear $currentYear,
        private readonly AcademicRecordUsage $usage,
    ) {}

    /** @return EloquentCollection<int, AcademicYear> */
    public function years(): EloquentCollection
    {
        return AcademicYear::query()->orderByDesc('starts_on')->orderByDesc('id')->get();
    }

    /**
     * Data for the Class schedules tab.
     *
     * @param  array{year?: ?string, grade?: ?string, section?: ?string, archived?: mixed}  $filters
     * @return array<string, mixed>
     */
    public function classSchedules(array $filters): array
    {
        $years = $this->years();
        $year = $this->currentYear->resolve($years, $filters['year'] ?? null);
        $current = $this->currentYear->pick($years);
        $showArchived = (bool) ($filters['archived'] ?? false);

        $sections = $year === null ? new EloquentCollection : Section::query()
            ->where('academic_year_id', $year->id)
            ->when(! $showArchived, fn ($query) => $query->where('status', 'active'))
            ->with([
                'gradeLevel',
                'allSectionSubjects' => fn ($query) => $query->with('subject'),
                'teachingAssignments' => fn ($query) => $query->where('is_active', true)->with('staffProfile.user'),
            ])
            ->withCount(['sectionAssignments as enrolled_count' => fn ($query) => $query->where('status', 'confirmed')->whereNull('ended_at')])
            ->get()
            ->sortBy(fn (Section $section): string => sprintf('%05d-%05d-%s', $section->gradeLevel->sequence, $section->sort_order, $section->name))
            ->values();

        $grades = GradeLevel::query()->orderBy('sequence')->get();
        $grade = isset($filters['grade']) ? $grades->firstWhere('public_id', $filters['grade']) : null;
        $section = isset($filters['section']) ? $sections->firstWhere('public_id', $filters['section']) : null;
        if ($section === null && isset($filters['section']) && $year !== null) {
            // An archived section reached through a direct link stays viewable.
            $section = Section::query()->where('academic_year_id', $year->id)->where('public_id', $filters['section'])
                ->with(['gradeLevel', 'allSectionSubjects.subject', 'teachingAssignments' => fn ($query) => $query->where('is_active', true)->with('staffProfile.user')])
                ->withCount(['sectionAssignments as enrolled_count' => fn ($query) => $query->where('status', 'confirmed')->whereNull('ended_at')])
                ->first();
        }
        if ($section !== null) {
            $grade = $section->gradeLevel;
        }
        $visibleSections = $grade === null ? $sections : $sections->where('grade_level_id', $grade->id)->values();

        $curricula = $year === null ? collect() : Curriculum::query()->where('academic_year_id', $year->id)->orderByDesc('version')->get();
        $published = $curricula->firstWhere('status', 'published');
        $missingCurriculumSubjects = 0;
        if ($section !== null && $published !== null) {
            $missingCurriculumSubjects = DB::table('curriculum_grade_subjects')
                ->where('curriculum_id', $published->id)
                ->where('grade_level_id', $section->grade_level_id)
                ->whereNotIn('subject_id', $section->allSectionSubjects->pluck('subject_id'))
                ->count();
        }
        $activeSections = $sections->where('status', 'active');
        $offerings = $activeSections->flatMap(fn (Section $item) => $item->allSectionSubjects->where('status', 'active'));
        $assignedOfferingIds = $activeSections->flatMap(fn (Section $item) => $item->teachingAssignments->where('assignment_type', 'subject_teacher')->pluck('section_subject_id'))->filter()->unique();

        return [
            'years' => $years,
            'year' => $year,
            'currentYear' => $current,
            'grades' => $grades,
            'grade' => $grade,
            'sections' => $sections,
            'visibleSections' => $visibleSections,
            'section' => $section,
            'showArchived' => $showArchived,
            'publishedCurriculum' => $published,
            'missingCurriculumSubjects' => $missingCurriculumSubjects,
            'draftCurriculum' => $curricula->firstWhere('status', 'draft'),
            'checklist' => [
                'sections' => $activeSections->count(),
                'advisers' => $activeSections->filter(fn (Section $item): bool => $item->teachingAssignments->contains('assignment_type', 'adviser'))->count(),
                'offerings' => $offerings->count(),
                'teachers' => $offerings->filter(fn ($offering): bool => $assignedOfferingIds->contains($offering->id))->count(),
            ],
            'sectionUsage' => $section ? $this->usage->for($section) : [],
            'offeringUsage' => $section ? $this->usage->forMany($section->allSectionSubjects) : [],
            'assignmentUsage' => $section ? $this->usage->forMany($section->teachingAssignments) : [],
            'staffProfiles' => $this->teachers(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    /**
     * Data for the Setup tab.
     *
     * @param  array{year?: ?string, archived?: mixed}  $filters
     * @return array<string, mixed>
     */
    public function setup(array $filters): array
    {
        $years = $this->years();
        $year = $this->currentYear->resolve($years, $filters['year'] ?? null);
        $grades = GradeLevel::query()->with('nextGradeLevel')->orderBy('sequence')->get();
        $subjects = Subject::query()->orderByDesc('is_active')->orderBy('name')->get();
        $showArchived = (bool) ($filters['archived'] ?? false);
        $sections = $year === null ? collect() : Section::query()->where('academic_year_id', $year->id)
            ->when(! $showArchived, fn ($query) => $query->where('status', 'active'))
            ->with('gradeLevel')
            ->withCount([
                'sectionSubjects as offerings_count',
                'sectionAssignments as enrolled_count' => fn ($query) => $query->where('status', 'confirmed')->whereNull('ended_at'),
            ])
            ->get()
            ->sortBy(fn (Section $section): string => sprintf('%05d-%05d-%s', $section->gradeLevel->sequence, $section->sort_order, $section->name))
            ->values();

        $publishedYears = DB::table('curricula')->where('status', 'published')->pluck('academic_year_id')->all();

        return [
            'years' => $years,
            'year' => $year,
            'currentYear' => $this->currentYear->pick($years),
            'publishedYearIds' => $publishedYears,
            'grades' => $grades,
            'subjects' => $subjects,
            'sections' => $sections,
            'showArchived' => $showArchived,
            'yearUsage' => $this->usage->forMany($years),
            'gradeUsage' => $this->usage->forMany($grades),
            'subjectUsage' => $this->usage->forMany($subjects),
            'sectionUsage' => $this->usage->forMany($sections),
        ];
    }

    /** @return Collection<int, StaffProfile> */
    public function teachers(): Collection
    {
        return StaffProfile::query()->with('user')
            ->whereHas('user.roles', fn ($query) => $query->where('code', 'teacher'))
            ->where(fn ($query) => $query->whereNull('active_until')->orWhereDate('active_until', '>=', today()))
            ->get()
            ->sortBy(fn (StaffProfile $staff): string => (string) $staff->user?->name)
            ->values();
    }
}
