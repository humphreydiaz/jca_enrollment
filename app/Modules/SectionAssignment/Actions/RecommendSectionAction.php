<?php

namespace App\Modules\SectionAssignment\Actions;

use App\Modules\Academics\Models\Section;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use Illuminate\Validation\ValidationException;

class RecommendSectionAction
{
    public function execute(Enrollment $enrollment): RecommendedSection
    {
        if ($enrollment->status !== 'approved') {
            throw ValidationException::withMessages(['enrollment' => 'Only an approved enrollment can receive a section recommendation.']);
        }

        if (SectionAssignment::query()->where('enrollment_id', $enrollment->id)->where('status', 'confirmed')->whereNull('ended_at')->exists()) {
            throw ValidationException::withMessages(['enrollment' => 'This enrollment already has a confirmed section.']);
        }

        $section = Section::query()
            ->where('academic_year_id', $enrollment->academic_year_id)
            ->where('grade_level_id', $enrollment->grade_level_id)
            ->where('status', 'active')
            ->withCount(['sectionAssignments as confirmed_count' => fn ($query) => $query->where('status', 'confirmed')->whereNull('ended_at')])
            ->get()
            ->filter(fn (Section $candidate) => $candidate->confirmed_count < $candidate->capacity)
            ->sortBy(fn (Section $candidate) => sprintf('%010d:%010d:%s:%010d', $candidate->confirmed_count, $candidate->sort_order, mb_strtolower($candidate->name), $candidate->id))
            ->first();

        return $section
            ? new RecommendedSection($section, 'Lowest confirmed count, then configured section order.', (int) $section->confirmed_count)
            : new RecommendedSection(null, 'No active section currently has available capacity.');
    }
}
