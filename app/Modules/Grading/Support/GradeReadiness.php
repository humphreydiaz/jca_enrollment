<?php

namespace App\Modules\Grading\Support;

/**
 * Whether an enrollment's subjects all have a Registrar/Admin-approved final grade.
 *
 * state: ready | pending | not_required | no_section | no_subjects
 * subjects: list of ['code', 'name', 'final_grade', 'status', 'label'] where status is
 *           approved | awaiting_approval | correction_pending | not_submitted | incomplete | no_grade
 */
final class GradeReadiness
{
    /** @param list<array{code: string, name: string, final_grade: ?int, status: string, label: string}> $subjects */
    public function __construct(
        public readonly string $state,
        public readonly array $subjects = [],
    ) {}

    public function isReady(): bool
    {
        return in_array($this->state, ['ready', 'not_required'], true);
    }

    public function approvedCount(): int
    {
        return count(array_filter($this->subjects, fn (array $subject): bool => $subject['status'] === 'approved'));
    }

    public function requiredCount(): int
    {
        return count($this->subjects);
    }

    /** One-sentence explanation used as the promotion review reason. */
    public function summary(): string
    {
        return match ($this->state) {
            'ready' => 'All '.$this->requiredCount().' subjects have approved final grades.',
            'not_required' => 'Preschool level: no numeric final grades are required.',
            'no_section' => 'The student has no confirmed section, so the subjects to grade are unknown.',
            'no_subjects' => 'The student\'s section has no subjects configured, so there are no final grades to check.',
            default => 'Waiting for approved final grades in '.($this->requiredCount() - $this->approvedCount()).' of '.$this->requiredCount().' subjects: '
                .collect($this->subjects)->reject(fn (array $subject): bool => $subject['status'] === 'approved')
                    ->map(fn (array $subject): string => $subject['name'].' ('.lcfirst($subject['label']).')')
                    ->join('; ').'.',
        };
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'ready' => $this->isReady(),
            'approved' => $this->approvedCount(),
            'required' => $this->requiredCount(),
            'summary' => $this->summary(),
            'subjects' => $this->subjects,
        ];
    }
}
