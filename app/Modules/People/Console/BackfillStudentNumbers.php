<?php

namespace App\Modules\People\Console;

use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Actions\IssueStudentNumberAction;
use App\Modules\People\Models\StudentProfile;
use Illuminate\Console\Command;

class BackfillStudentNumbers extends Command
{
    protected $signature = 'students:backfill-numbers {--dry-run : List what would be issued without writing}';

    protected $description = 'Issue a permanent student number to existing learners who submitted an application but have none yet';

    public function handle(IssueStudentNumberAction $issue): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Learners who have a live (non-terminal) submitted application but no
        // number yet — i.e. everyone currently expected to sign in. Terminal
        // applicants (rejected, withdrawn, cancelled) are left out; they get a
        // number only if they re-apply. Ordered by first submission so the
        // sequence within each entry year reflects the historical order.
        $profiles = StudentProfile::query()
            ->whereNull('student_number')
            ->whereHas('enrollments', fn ($query) => $query
                ->whereNotNull('submitted_at')
                ->whereNotIn('status', Enrollment::TERMINAL_STATUSES))
            ->with(['enrollments' => fn ($query) => $query
                ->whereNotNull('submitted_at')
                ->whereNotIn('status', Enrollment::TERMINAL_STATUSES)
                ->with('academicYear')
                ->orderBy('submitted_at')])
            ->get()
            ->sortBy(fn (StudentProfile $profile) => $profile->enrollments->first()?->submitted_at)
            ->values();

        if ($profiles->isEmpty()) {
            $this->info('No learners require a student number.');

            return self::SUCCESS;
        }

        $issued = 0;

        foreach ($profiles as $profile) {
            $entry = $profile->enrollments->first();
            $entryYear = $entry?->academicYear;

            if ($entryYear === null) {
                $this->warn("Skipped {$profile->full_name}: the submitted application has no academic year.");

                continue;
            }

            if ($dryRun) {
                $this->line("Would issue for {$profile->full_name} (entry {$entryYear->name}).");

                continue;
            }

            $number = $issue->execute($profile, $entryYear);
            $this->line("{$number}  {$profile->full_name}");
            $issued++;
        }

        $this->info($dryRun
            ? "Dry run: {$profiles->count()} learner(s) would receive a student number."
            : "Issued {$issued} student number(s).");

        return self::SUCCESS;
    }
}
