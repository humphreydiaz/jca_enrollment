<?php

namespace Database\Seeders\Billing;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Billing\Models\ExamPeriod;
use App\Modules\Shared\Audit\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The four quarterly examinations from the JCSGO Christian Academy R1 School Calendar 2026-2027
 * (prepared by the School Head, recommended by SDO 1 Pangasinan, approved by QAD Region 1).
 *
 * SY 2026-2027 uses the calendar's dates exactly. No calendar was supplied for SY 2025-2026, so its dates
 * follow the same pattern one school year earlier: the same week of the school year and the same weekdays,
 * kept off national holidays (Q1 still ends the day before Ninoy Aquino Day, as on the 2026-2027 calendar).
 * Mid-quarter exams are school assessments only; they are not permit periods and are not added, because
 * the Quarterly and Semiannual due schedules read the examination periods in date order.
 *
 * Each period gets the system's usual permit rules: eligibility cutoff 10 days before the first exam day,
 * permit issuing from 30 days before until the last exam day. Status follows today's date.
 * Safe to run again: an existing period code in that year is never overwritten, and a year that already
 * has four or more examination periods is left alone.
 *
 *   php artisan db:seed --class="Database\Seeders\Billing\SchoolCalendarExamPeriodsSeeder"
 */
class SchoolCalendarExamPeriodsSeeder extends Seeder
{
    /** @var array<string, list<array{0: string, 1: string, 2: string, 3: string}>> school year code => [code, name, first day, last day] */
    public const CALENDAR = [
        '2025-2026' => [
            ['Q1', 'First Quarterly Examination', '2025-08-19', '2025-08-20'],
            ['Q2', 'Second Quarterly Examination', '2025-10-20', '2025-10-21'],
            ['Q3', 'Third Quarterly Examination', '2026-01-22', '2026-01-23'],
            ['Q4', 'Fourth Quarterly Examination', '2026-03-16', '2026-03-17'],
        ],
        '2026-2027' => [
            ['Q1', 'First Quarterly Examination', '2026-08-19', '2026-08-20'],
            ['Q2', 'Second Quarterly Examination', '2026-10-19', '2026-10-20'],
            ['Q3', 'Third Quarterly Examination', '2027-01-21', '2027-01-22'],
            ['Q4', 'Fourth Quarterly Examination', '2027-03-15', '2027-03-16'],
        ],
    ];

    public function run(): void
    {
        $actor = User::query()->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->whereIn('code', ['admin', 'accounting']))
            ->orderBy('id')->first();
        $today = CarbonImmutable::today('Asia/Manila');

        foreach (self::CALENDAR as $code => $periods) {
            $year = AcademicYear::query()->where('code', $code)->first();
            if ($year === null) {
                $this->command?->warn("School Year {$code} does not exist in this database; skipped.");

                continue;
            }
            if (ExamPeriod::query()->where('academic_year_id', $year->id)->count() >= 4) {
                $this->command?->info("{$year->name}: already has four or more examination periods; left unchanged.");

                continue;
            }

            $created = DB::transaction(function () use ($year, $periods, $actor, $today): int {
                $created = 0;
                foreach ($periods as [$periodCode, $name, $first, $last]) {
                    $startsOn = CarbonImmutable::parse($first);
                    $endsOn = CarbonImmutable::parse($last);
                    if ($startsOn->lt(CarbonImmutable::parse($year->starts_on)) || $endsOn->gt(CarbonImmutable::parse($year->ends_on))) {
                        $this->command?->warn("{$year->name}: {$name} ({$first} to {$last}) falls outside the school year's dates; skipped.");

                        continue;
                    }
                    $issueOpens = $startsOn->subDays(30);

                    $period = ExamPeriod::query()->firstOrCreate(
                        ['academic_year_id' => $year->id, 'code' => $periodCode],
                        [
                            'name' => $name,
                            'starts_on' => $startsOn->toDateString(),
                            'ends_on' => $endsOn->toDateString(),
                            'eligibility_cutoff_on' => $startsOn->subDays(10)->toDateString(),
                            'issue_opens_on' => $issueOpens->toDateString(),
                            'issue_closes_on' => $endsOn->toDateString(),
                            'status' => match (true) {
                                $endsOn->lt($today) => 'closed',
                                $issueOpens->lte($today) => 'open',
                                default => 'planned',
                            },
                        ],
                    );
                    if ($period->wasRecentlyCreated) {
                        $created++;
                        app(AuditRecorder::class)->record($actor, 'billing.exam_period.created', $period, ['source' => 'school_calendar_seeder']);
                    }
                }

                return $created;
            });
            $this->command?->info("{$year->name}: added {$created} quarterly examination period(s).");
        }
    }
}
