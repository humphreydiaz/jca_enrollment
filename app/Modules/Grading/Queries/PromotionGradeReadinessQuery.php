<?php

namespace App\Modules\Grading\Queries;

use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Grading\Support\GradeReadiness;
use App\Modules\Grading\Support\NumericGradeLevels;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Checks that every current subject of a student's section has a complete final grade that the
 * Registrar or Admin approved. Archived (dropped) section subjects are not required.
 */
class PromotionGradeReadinessQuery
{
    public function forEnrollment(Enrollment $enrollment): GradeReadiness
    {
        return $this->forMany(collect([$enrollment]))[$enrollment->id];
    }

    /**
     * @param  Collection<int, Enrollment>  $enrollments
     * @return array<int, GradeReadiness> enrollment id => readiness
     */
    public function forMany(Collection $enrollments): array
    {
        if ($enrollments->isEmpty()) {
            return [];
        }
        $enrollments = EloquentCollection::make($enrollments->all())->loadMissing('gradeLevel');
        $ids = $enrollments->pluck('id')->all();

        $sectionByEnrollment = SectionAssignment::query()->whereIn('enrollment_id', $ids)
            ->where('status', 'confirmed')->whereNull('ended_at')
            ->pluck('section_id', 'enrollment_id');
        $offeringsBySection = SectionSubject::query()->whereIn('section_id', $sectionByEnrollment->values()->unique())
            ->where('status', 'active')->with('subject')->get()->groupBy('section_id');
        $grades = StudentSubjectGrade::query()->whereIn('enrollment_id', $ids)
            ->withCount(['corrections as pending_corrections_count' => fn ($query) => $query->where('status', 'pending')])
            ->get()->groupBy('enrollment_id');

        $result = [];
        foreach ($enrollments as $enrollment) {
            if (! NumericGradeLevels::includes($enrollment->gradeLevel)) {
                $result[$enrollment->id] = new GradeReadiness('not_required');

                continue;
            }
            $sectionId = $sectionByEnrollment->get($enrollment->id);
            if ($sectionId === null) {
                $result[$enrollment->id] = new GradeReadiness('no_section');

                continue;
            }
            $offerings = $offeringsBySection->get($sectionId, collect())
                ->sortBy(fn (SectionSubject $offering): string => $offering->subject->name)->values();
            if ($offerings->isEmpty()) {
                $result[$enrollment->id] = new GradeReadiness('no_subjects');

                continue;
            }
            $studentGrades = $grades->get($enrollment->id, collect())->keyBy('subject_id');
            $subjects = $offerings->map(function (SectionSubject $offering) use ($studentGrades): array {
                $grade = $studentGrades->get($offering->subject_id);
                [$status, $label] = $this->status($grade);

                return [
                    'code' => $offering->subject->code,
                    'name' => $offering->subject->name,
                    'final_grade' => $grade?->final_grade,
                    'status' => $status,
                    'label' => $label,
                ];
            })->all();
            $ready = collect($subjects)->every(fn (array $subject): bool => $subject['status'] === 'approved');
            $result[$enrollment->id] = new GradeReadiness($ready ? 'ready' : 'pending', $subjects);
        }

        return $result;
    }

    /** @return array{string, string} */
    private function status(?StudentSubjectGrade $grade): array
    {
        if ($grade === null) {
            return ['no_grade', 'No grade yet'];
        }
        $quarters = collect([$grade->quarter_1, $grade->quarter_2, $grade->quarter_3, $grade->quarter_4])->filter(fn ($value) => $value !== null)->count();

        return match (true) {
            $grade->status === 'finalized' && (int) $grade->pending_corrections_count > 0 => ['correction_pending', 'Correction requested'],
            $grade->status === 'finalized' && $grade->final_grade !== null => ['approved', 'Approved'],
            $grade->status === 'submitted' => ['awaiting_approval', 'Waiting for Registrar approval'],
            $quarters < 4 => ['incomplete', 'Incomplete: '.$quarters.' of 4 quarters'],
            default => ['not_submitted', 'Not yet submitted by the teacher'],
        };
    }
}
