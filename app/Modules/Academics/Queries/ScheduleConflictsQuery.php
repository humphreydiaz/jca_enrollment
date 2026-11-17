<?php

namespace App\Modules\Academics\Queries;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Academics\Support\ClassSchedule;
use Illuminate\Support\Collection;

/**
 * D-068 (pre-final panel: "conflict schedules, nakikita ba? need dapat meron yon"; "also need makita ng
 * teachers ang kanilang schedules"). Reads the active class offerings of one school year and reports:
 * - teacher conflicts: one teacher in two classes that meet at the same time;
 * - section conflicts: two subjects of one section that meet at the same time;
 * - unchecked: offerings whose schedule is missing or not in a readable format, so overlaps can't be checked.
 * Rooms are not tracked (Phase 14), so room clashes are out of scope.
 */
class ScheduleConflictsQuery
{
    /** @return array{teacher: list<array<string, mixed>>, section: list<array<string, mixed>>, unchecked: list<array<string, mixed>>, offerings: int} */
    public function forYear(AcademicYear $year): array
    {
        $offerings = $this->offerings($year);

        $teacherConflicts = [];
        $byTeacher = [];
        foreach ($offerings as $row) {
            foreach ($row['teachers'] as $staffId => $name) {
                $byTeacher[$staffId]['name'] = $name;
                $byTeacher[$staffId]['rows'][] = $row;
            }
        }
        foreach ($byTeacher as $teacher) {
            foreach ($this->overlappingPairs($teacher['rows']) as [$a, $b]) {
                $teacherConflicts[] = ['teacher' => $teacher['name'], 'first' => $a, 'second' => $b];
            }
        }

        $sectionConflicts = [];
        foreach ($offerings->groupBy(fn (array $row) => $row['section']->id) as $rows) {
            foreach ($this->overlappingPairs($rows->all()) as [$a, $b]) {
                $sectionConflicts[] = ['section' => $a['section'], 'first' => $a, 'second' => $b];
            }
        }

        return [
            'teacher' => $teacherConflicts,
            'section' => $sectionConflicts,
            'unchecked' => $offerings->filter(fn (array $row) => $row['schedule'] === null)->values()->all(),
            'offerings' => $offerings->count(),
        ];
    }

    /**
     * A teacher's own weekly classes for one school year, with any overlap between them marked.
     *
     * @return array{days: array<int, list<array<string, mixed>>>, unscheduled: list<array<string, mixed>>, advisory: list<string>, conflicts: int}
     */
    public function forTeacher(User $user, AcademicYear $year): array
    {
        $mine = TeachingAssignment::query()->active()->forUser($user)
            ->whereHas('section', fn ($section) => $section->where('academic_year_id', $year->id))
            ->with(['section.gradeLevel', 'sectionSubject.subject'])
            ->get();

        $rows = $mine->where('assignment_type', 'subject_teacher')
            ->filter(fn (TeachingAssignment $role) => $role->sectionSubject !== null && $role->sectionSubject->status === 'active')
            ->map(fn (TeachingAssignment $role) => $this->row($role->sectionSubject, $role->section))
            ->values();

        $clashing = [];
        foreach ($this->overlappingPairs($rows->all()) as [$a, $b]) {
            $clashing[$a['id']][] = $b;
            $clashing[$b['id']][] = $a;
        }

        $days = [];
        foreach ($rows->filter(fn (array $row) => $row['schedule'] !== null) as $row) {
            foreach ($row['schedule']->days as $day) {
                $days[$day][] = $row + ['clashes_with' => $clashing[$row['id']] ?? []];
            }
        }
        ksort($days);
        foreach ($days as &$entries) {
            usort($entries, fn (array $x, array $y) => strcmp($x['schedule']->startsAt, $y['schedule']->startsAt));
        }
        unset($entries);

        return [
            'days' => $days,
            'unscheduled' => $rows->filter(fn (array $row) => $row['schedule'] === null)->values()->all(),
            'advisory' => $mine->where('assignment_type', 'adviser')->map(fn (TeachingAssignment $role) => $role->section->gradeLevel->name.' – '.$role->section->name)->unique()->values()->all(),
            'conflicts' => count($clashing),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function offerings(AcademicYear $year): Collection
    {
        return SectionSubject::query()
            ->where('status', 'active')
            ->whereHas('section', fn ($section) => $section->where('academic_year_id', $year->id))
            ->with(['section.gradeLevel', 'subject', 'teachingAssignments' => fn ($roles) => $roles->active()->where('assignment_type', 'subject_teacher')->with('staffProfile.user')])
            ->get()
            ->map(function (SectionSubject $offering): array {
                $row = $this->row($offering, $offering->section);
                $row['teachers'] = $offering->teachingAssignments->mapWithKeys(fn (TeachingAssignment $role) => [$role->staff_profile_id => $role->staffProfile?->user?->name ?? 'Unnamed teacher'])->all();

                return $row;
            });
    }

    /** @return array<string, mixed> */
    private function row(SectionSubject $offering, $section): array
    {
        try {
            $schedule = ClassSchedule::fromRecord($offering);
        } catch (\Throwable) {
            $schedule = null; // A malformed stored time is reported as "cannot be checked", never a crash.
        }

        return [
            'id' => $offering->id,
            'subject' => $offering->subject?->name ?? 'Subject',
            'section' => $section,
            'section_label' => $section->gradeLevel->name.' – '.$section->name,
            'schedule' => $schedule,
            'schedule_label' => $schedule?->label() ?? ($offering->schedule_text ?: 'No schedule set'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{0: array<string, mixed>, 1: array<string, mixed>}>
     */
    private function overlappingPairs(array $rows): array
    {
        $pairs = [];
        $count = count($rows);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $rows[$i]['schedule'];
                $b = $rows[$j]['schedule'];
                if ($a !== null && $b !== null && $rows[$i]['id'] !== $rows[$j]['id'] && $a->overlaps($b)) {
                    $pairs[] = [$rows[$i], $rows[$j]];
                }
            }
        }

        return $pairs;
    }
}
