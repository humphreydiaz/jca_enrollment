<?php

namespace Database\Seeders\Capstone;

use App\Models\User;
use App\Modules\Academics\Actions\CreateCurriculumDraftAction;
use App\Modules\Academics\Actions\CreateTeachingAssignmentAction;
use App\Modules\Academics\Actions\PublishCurriculumAction;
use App\Modules\Academics\Actions\SaveAcademicCatalogAction;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\SectionSubject;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Academics\Support\ClassSchedule;
use App\Modules\Billing\Actions\CreateAssessmentAction;
use App\Modules\Billing\Actions\CreateDraftPaymentAction;
use App\Modules\Billing\Actions\FinalizeAssessmentAction;
use App\Modules\Billing\Actions\IssueExamPermitAction;
use App\Modules\Billing\Actions\ManageFeeScheduleAction;
use App\Modules\Billing\Actions\ManagePaymentPlanOptionAction;
use App\Modules\Billing\Actions\PostPaymentAction;
use App\Modules\Billing\Actions\SaveBillingConfigurationAction;
use App\Modules\Billing\Actions\SelectPaymentPlanAction;
use App\Modules\Billing\Calculations\PermitEligibilityCalculator;
use App\Modules\Billing\Models\ExamPeriod;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Billing\Models\FeeScheduleItem;
use App\Modules\Billing\Models\FeeType;
use App\Modules\Billing\Models\FinancialPrintSetting;
use App\Modules\Billing\Models\SchoolNonBusinessDay;
use App\Modules\Communications\Actions\CreateAnnouncementAction;
use App\Modules\Enrollment\Actions\IssueWalkInAccessAction;
use App\Modules\Enrollment\Actions\SaveEnrollmentDraftAction;
use App\Modules\Enrollment\Actions\TransitionEnrollmentAction;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Grading\Actions\SaveQuarterGradesAction;
use App\Modules\Grading\Support\NumericGradeLevels;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Actions\ContactNormalizer;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Actions\IssueStaffNumberAction;
use App\Modules\People\Models\StaffProfile;
use App\Modules\People\Support\RandomThreeDigitSuffix;
use App\Modules\People\Support\StaffNumber;
use App\Modules\Requirements\Actions\RecordHardCopyReceiptAction;
use App\Modules\Requirements\Actions\ReviewRequirementAction;
use App\Modules\SectionAssignment\Actions\ConfirmSectionAssignmentAction;
use App\Modules\Shared\Audit\AuditRecorder;
use App\Modules\StudentSupport\Actions\CreateSupportCaseAction;
use App\Modules\StudentSupport\Actions\ManageSupportCaseAction;
use Carbon\CarbonImmutable;
use Closure;
use Database\Seeders\Academics\GradeLevelSeeder;
use Database\Seeders\Identity\RoleSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Capstone demonstration data for a FRESH database: School Year 2026-2027 as if the school had
 * used the system since April 2026.
 *
 * Everything is created through the same actions the screens use, with the clock set back to
 * when each step "happened" (the Admin opens the year in April, families walk in from May,
 * Accounting posts payments on their due dates, teachers encode Q1 grades in late August, and
 * so on). Statuses, Student IDs, receipts, permits, audit history, and timestamps therefore look
 * like real accumulated use. Nothing is dated later than 2026-10-06.
 *
 * Staff: the Admin, Registrar, and Accounting accounts are recreated from the snapshot written by
 * `php artisan capstone:snapshot-staff` before `migrate:fresh` (same names, emails, mobiles,
 * Staff IDs). Without a snapshot it creates the hosted site's staff: Maria Dela Cruz (Admin, 26-JCA-100),
 * Jenny Santos (Registrar, 26-JCA-101) and Angelica Garcia (Accounting, 26-JCA-102). Accounts without a
 * 26-JCA-000 Staff ID (e.g. HST-REG-001) get the role's usual number. Every account's password is Password12345.
 *
 * Learners, parents, and teachers are fictional (see CapstoneRoster): emails on the non-deliverable
 * "gmail.test" domain and no mobile numbers, so no SMS or mail can reach a real person.
 *
 *   php artisan migrate:fresh --force
 *   php artisan db:seed --class="Database\Seeders\Capstone\CapstoneDemoSeeder" --force
 */
class CapstoneDemoSeeder extends Seeder
{
    public const PASSWORD = 'Password12345';

    /** Written by capstone:snapshot-staff on the local disk (storage/app/private). */
    public const SNAPSHOT_PATH = 'capstone/staff-snapshot.json';

    private const TIMEZONE = 'Asia/Manila';

    /** Nothing is dated after this moment. */
    private const LATEST = '2026-10-06 17:00';

    private const YEAR = [
        'code' => '2026-2027',
        'name' => 'School Year 2026-2027',
        'starts_on' => '2026-06-08',
        'ends_on' => '2027-04-08',
        'enrollment_opens_on' => '2026-05-04',
        // Open through October so the late (October) walk-ins in the roster are still inside the window.
        'enrollment_closes_on' => '2026-10-31',
    ];

    private const EXAM_PERIODS = [
        ['Q1', 'First Quarterly Examination', '2026-08-19', '2026-08-20'],
        ['Q2', 'Second Quarterly Examination', '2026-10-19', '2026-10-20'],
        ['Q3', 'Third Quarterly Examination', '2027-01-21', '2027-01-22'],
        ['Q4', 'Fourth Quarterly Examination', '2027-03-15', '2027-03-16'],
    ];

    /** Busy windows of the preschool advisers' own batches (all weekdays). */
    private const PRESCHOOL_HOURS = [
        'NUR.Hope' => ['11:00', '13:00'],
        'JK.Joy' => ['08:00', '10:30'],
        'JK.Peace' => ['13:00', '15:30'],
        'SK.Love' => ['08:00', '11:00'],
        'SK.Wisdom' => ['11:30', '14:30'],
    ];

    /** @var list<array{at: CarbonImmutable, seq: int, run: Closure}> */
    private array $events = [];

    private int $sequence = 0;

    private CarbonImmutable $clock;

    private CarbonImmutable $latest;

    private User $admin;

    private User $registrar;

    private User $accounting;

    private AcademicYear $year;

    /** @var Collection<string, GradeLevel> */
    private Collection $grades;

    /** @var Collection<string, Section> keyed "G7.Isaiah" */
    private Collection $sections;

    /** @var array<string, array{user: User, staff: StaffProfile, data: array<string, mixed>}> keyed "G7.Isaiah" */
    private array $teachers = [];

    /** @var array<string, FeeSchedule> */
    private array $schedules = [];

    /** @var array<string, ExamPeriod> */
    private array $periods = [];

    /** @var array<string, array<string, mixed>> per learner key */
    private array $state = [];

    /** @var array<string, string> */
    private array $nonBusinessDays = [];

    private int $referenceCounter = 0;

    private PinnedThreeDigitSuffix $suffix;

    /** @var list<array{0: string, 1: string, 2: string, 3: string}> printed at the end */
    private array $logins = [];

    public function run(): void
    {
        $this->latest = CarbonImmutable::parse(self::LATEST, self::TIMEZONE);
        if (CarbonImmutable::now(self::TIMEZONE)->lt($this->latest)) {
            throw new RuntimeException('The capstone data is dated up to '.self::LATEST.'; run it on or after that date.');
        }
        if (AcademicYear::query()->exists() || Enrollment::query()->exists()) {
            throw new RuntimeException('This seeder builds a whole school year and needs a fresh database. Run "php artisan migrate:fresh --force" first (after a backup).');
        }
        $snapshot = $this->readSnapshot();
        $this->suffix = new PinnedThreeDigitSuffix;
        app()->instance(RandomThreeDigitSuffix::class, $this->suffix);
        $this->nonBusinessDays = CapstoneFinance::NON_BUSINESS_DAYS;

        try {
            DB::transaction(function () use ($snapshot): void {
                $this->setUpSchool($snapshot);
                $this->scheduleSchoolYear();
                $this->runTimeline();
            });
        } finally {
            app()->forgetInstance(RandomThreeDigitSuffix::class);
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }

        if ($snapshot !== null) {
            Storage::disk('local')->delete(self::SNAPSHOT_PATH);
        }
        $this->report();
    }

    // ---------------------------------------------------------------- clock and timeline

    private function travel(CarbonImmutable|string $at): CarbonImmutable
    {
        $moment = $at instanceof CarbonImmutable ? $at : CarbonImmutable::parse($at, self::TIMEZONE);
        if ($moment->gt($this->latest)) {
            throw new RuntimeException('Capstone timeline step dated after '.self::LATEST.': '.$moment->toDateTimeString());
        }
        $this->clock = $moment;
        Carbon::setTestNow(Carbon::instance($moment->toDateTime())->setTimezone(config('app.timezone')));
        CarbonImmutable::setTestNow($moment->setTimezone(config('app.timezone')));

        return $moment;
    }

    private function at(CarbonImmutable|string $when, Closure $run): void
    {
        $moment = $when instanceof CarbonImmutable ? $when : CarbonImmutable::parse($when, self::TIMEZONE);
        if (isset($this->clock) && $moment->lt($this->clock)) {
            $moment = $this->clock->addMinutes(2);
        }
        $this->events[] = ['at' => $moment, 'seq' => $this->sequence++, 'run' => $run];
    }

    private function runTimeline(): void
    {
        while ($this->events !== []) {
            $next = 0;
            foreach ($this->events as $index => $event) {
                $best = $this->events[$next];
                if ($event['at']->lt($best['at']) || ($event['at']->eq($best['at']) && $event['seq'] < $best['seq'])) {
                    $next = $index;
                }
            }
            $event = $this->events[$next];
            array_splice($this->events, $next, 1);
            $this->travel($event['at']);
            ($event['run'])();
        }
    }

    private function isBusinessDay(CarbonImmutable $day): bool
    {
        return ! $day->isWeekend() && ! isset($this->nonBusinessDays[$day->toDateString()]);
    }

    /** The first business day strictly after the given day, at the given time. */
    private function nextBusinessDay(CarbonImmutable $day, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));
        $next = $day->addDay()->setTime($hour, $minute);
        while (! $this->isBusinessDay($next)) {
            $next = $next->addDay();
        }

        return $next;
    }

    /** The business day on or before the given day. */
    private function businessDayOnOrBefore(CarbonImmutable $day): CarbonImmutable
    {
        while (! $this->isBusinessDay($day)) {
            $day = $day->subDay();
        }

        return $day;
    }

    /** A stable number in [0, $modulo) for a name, so reruns produce the same story. */
    private function pick(string $seed, int $modulo): int
    {
        return (int) (crc32($seed) % max(1, $modulo));
    }

    // ---------------------------------------------------------------- setup (April)

    /** @param array<string, mixed>|null $snapshot */
    private function setUpSchool(?array $snapshot): void
    {
        $this->travel('2026-04-13 07:52');
        $this->call([RoleSeeder::class, GradeLevelSeeder::class]);
        $this->grades = GradeLevel::query()->get()->keyBy('code');

        $this->createSchoolStaff($snapshot);

        // The Admin sets up the school year; this also installs the year's requirement rules.
        $this->travel('2026-04-14 09:10');
        /** @var AcademicYear $year */
        $year = app(SaveAcademicCatalogAction::class)->execute(new AcademicYear, [...self::YEAR, 'status' => 'planned'], $this->admin, 'academics.academic_year.created');
        $this->year = $year;

        // Curriculum from the official class program; publishing creates the 20 sections and their subjects.
        $this->travel('2026-04-20 10:30');
        $curriculum = app(CreateCurriculumDraftAction::class)->execute($this->year, 'JCA 2026-2027 class program', null, $this->admin, useOfficialDefault: true);
        $this->travel('2026-04-27 14:05');
        app(PublishCurriculumAction::class)->execute($curriculum, $this->admin);
        $this->sections = Section::query()->where('academic_year_id', $this->year->id)->with('gradeLevel')->get()
            ->keyBy(fn (Section $section) => $section->gradeLevel->code.'.'.$section->name);

        $this->travel('2026-05-04 07:45');
        // Enrollment needs both the year opened and its enrollment switch on (2026_11_01 migration).
        app(SaveAcademicCatalogAction::class)->execute($this->year, ['status' => 'open', 'enrollment_is_open' => true], $this->admin, 'academics.academic_year.updated');
    }

    /** @param array<string, mixed>|null $snapshot */
    private function createSchoolStaff(?array $snapshot): void
    {
        $people = collect($snapshot['accounts'] ?? [])
            ->map(fn (array $account) => [...$account, 'roles' => array_values(array_intersect($account['roles'] ?? [], ['admin', 'registrar', 'accounting']))])
            ->filter(fn (array $account) => $account['roles'] !== [] && filled($account['name'] ?? null))
            ->sortBy(fn (array $account) => in_array('admin', $account['roles'], true) ? 0 : 1)
            ->values();

        // The hosted capstone's own staff (Hostinger backup of 2026-10-07), used when no snapshot was taken.
        if (! $people->contains(fn (array $account) => in_array('admin', $account['roles'], true))) {
            $people->prepend(['name' => 'Maria Dela Cruz', 'email' => 'maria@gmail.com', 'mobile' => null, 'employee_number' => '26-JCA-100', 'position_title' => 'School Administrator', 'roles' => ['admin']]);
        }
        if (! $people->contains(fn (array $account) => in_array('registrar', $account['roles'], true))) {
            $people->push(['name' => 'Jenny Santos', 'email' => 'jenny@gmail.com', 'mobile' => null, 'employee_number' => '26-JCA-101', 'position_title' => 'Registrar', 'roles' => ['registrar']]);
        }
        if (! $people->contains(fn (array $account) => in_array('accounting', $account['roles'], true))) {
            $people->push(['name' => 'Angelica Garcia', 'email' => 'angelica@gmail.com', 'mobile' => null, 'employee_number' => '26-JCA-102', 'position_title' => 'Accounting Staff', 'roles' => ['accounting']]);
        }

        // Sign-in needs a Staff ID in the 26-JCA-000 format. Accounts that never had one (or had an
        // older format such as HST-REG-001) get the role's usual number when it is free.
        $preferred = ['admin' => '26-JCA-100', 'registrar' => '26-JCA-101', 'accounting' => '26-JCA-102'];
        $taken = $people->pluck('employee_number')->filter(fn ($number) => filled($number) && StaffNumber::looksLike((string) $number))
            ->map(fn ($number) => StaffNumber::normalize((string) $number))->all();
        $people = $people->map(function (array $account) use ($preferred, &$taken): array {
            $number = $account['employee_number'] ?? null;
            if (filled($number) && StaffNumber::looksLike((string) $number)) {
                return $account;
            }
            $candidate = $preferred[$account['roles'][0]] ?? null;
            $account['employee_number'] = $candidate !== null && ! in_array($candidate, $taken, true) ? $candidate : null;
            $taken[] = $account['employee_number'];

            return $account;
        });

        $actor = null;
        foreach ($people as $index => $account) {
            if ($index > 0) {
                $this->travel($index === 1 ? '2026-04-13 08:20' : '2026-04-13 08:'.(20 + $index * 4));
            }
            $user = $this->createStaffUser($account['name'], $account['email'] ?? null, $account['mobile'] ?? null, $account['roles'],
                $account['employee_number'] ?? null, $account['position_title'] ?? $this->positionFor($account['roles']), '2026-04-13', $actor);
            $actor ??= $user;
            if (in_array('admin', $account['roles'], true)) {
                $this->admin ??= $user;
            }
            if (in_array('registrar', $account['roles'], true)) {
                $this->registrar ??= $user;
            }
            if (in_array('accounting', $account['roles'], true)) {
                $this->accounting ??= $user;
            }
        }
    }

    /** @param list<string> $roles */
    private function positionFor(array $roles): string
    {
        return match (true) {
            in_array('admin', $roles, true) => 'School Administrator',
            in_array('registrar', $roles, true) => 'Registrar',
            in_array('accounting', $roles, true) => 'Accounting Staff',
            default => 'Teacher',
        };
    }

    /** @param list<string> $roles */
    private function createStaffUser(string $name, ?string $email, ?string $mobile, array $roles, ?string $staffNumber, string $position, string $employedOn, ?User $actor): User
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => ContactNormalizer::email($email),
            'mobile' => ContactNormalizer::mobile($mobile),
            'password' => self::PASSWORD,
            'status' => AccountStatus::Active,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);
        if ($user->email !== null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
        app(AssignRolesAction::class)->execute($user, $roles, $actor);
        $staff = StaffProfile::query()->create([
            'user_id' => $user->id,
            'position_title' => $position,
            'active_from' => $employedOn,
            'employee_number' => filled($staffNumber) && StaffNumber::looksLike((string) $staffNumber) ? StaffNumber::normalize((string) $staffNumber) : null,
        ]);
        if ($staff->employee_number === null) {
            app(IssueStaffNumberAction::class)->execute($staff, CarbonImmutable::parse($employedOn), $actor);
        }
        app(AuditRecorder::class)->record($actor ?? $user, 'identity.user.created', $user, ['roles' => implode(',', $roles), 'source' => 'staff_setup']);
        $this->logins[] = [implode(', ', array_map(fn ($role) => Str::headline($role), $roles)), $name, (string) $staff->fresh()->employee_number, $user->email ?? '—'];

        return $user;
    }

    // ---------------------------------------------------------------- the school year

    private function scheduleSchoolYear(): void
    {
        // Admin: announcement that enrollment is open (May 4), finance setup (May 6-7), teachers (May 5-8).
        foreach (CapstoneRoster::ANNOUNCEMENTS as $announcement) {
            $this->at($announcement['at'], fn () => app(CreateAnnouncementAction::class)->execute([
                'title' => $announcement['title'], 'body' => $announcement['body'], 'delivery' => 'portal', 'sms_body' => null, 'status' => 'published',
            ], $announcement['roles'], $this->admin));
        }
        $this->at('2026-05-05 08:30', fn () => $this->createTeachers());
        $this->at('2026-05-06 09:00', fn () => $this->createFinanceConfiguration());
        $this->at('2026-06-01 13:30', fn () => $this->assignTeachers());

        foreach (CapstoneRoster::LEARNERS as $learner) {
            $this->scheduleEnrolledLearner($learner);
        }
        foreach (CapstoneRoster::PENDING as $learner) {
            $this->schedulePendingLearner($learner);
        }

        // Examination periods follow the calendar: permits open 30 days before, close on the last exam day.
        $this->at('2026-07-20 07:40', fn () => $this->setPeriodStatus('Q1', 'open'));
        $this->at('2026-08-21 07:40', fn () => $this->setPeriodStatus('Q1', 'closed'));
        $this->at('2026-09-21 07:40', fn () => $this->setPeriodStatus('Q2', 'open'));
        $this->scheduleExamPermits('Q1', ['2026-08-10', '2026-08-11', '2026-08-12', '2026-08-13', '2026-08-14', '2026-08-17', '2026-08-18']);
        $this->scheduleExamPermits('Q2', ['2026-10-05', '2026-10-06']);
        $this->scheduleFirstQuarterGrades();
    }

    private function createTeachers(): void
    {
        foreach (CapstoneRoster::TEACHERS as $index => $teacher) {
            $this->travel($this->clock->addMinutes($index === 0 ? 0 : 6 + $this->pick($teacher['last'], 5)));
            $name = $teacher['first'].' '.$teacher['last'];
            $email = Str::lower(Str::of($teacher['first'])->replace(['Ma. ', ' '], ['', ''])->ascii().'.'.Str::of($teacher['last'])->replace(' ', '')->ascii()).'@'.CapstoneRoster::EMAIL_DOMAIN;
            $this->suffix->pin($teacher['staff_number'] ?? null);
            $user = $this->createStaffUser($name, $email, null, ['teacher'], null, 'Teacher', $teacher['employed_on'], $this->admin);
            $this->teachers[$teacher['grade'].'.'.$teacher['section']] = ['user' => $user, 'staff' => StaffProfile::query()->where('user_id', $user->id)->sole(), 'data' => $teacher];
        }
    }

    private function createFinanceConfiguration(): void
    {
        $save = app(SaveBillingConfigurationAction::class);
        foreach (CapstoneFinance::FEE_TYPES as $type) {
            $save->execute(new FeeType, [...$type, 'is_active' => true], $this->admin, 'billing.fee_type.created');
        }
        $types = FeeType::query()->get()->keyBy('code');

        foreach (CapstoneFinance::NON_BUSINESS_DAYS as $date => $label) {
            $save->execute(new SchoolNonBusinessDay, ['academic_year_id' => $this->year->id, 'calendar_date' => $date, 'label' => $label], $this->admin, 'billing.non_business_day.created');
        }
        foreach (self::EXAM_PERIODS as [$code, $name, $first, $last]) {
            $starts = CarbonImmutable::parse($first);
            $this->periods[$code] = $save->execute(new ExamPeriod, [
                'academic_year_id' => $this->year->id, 'code' => $code, 'name' => $name, 'starts_on' => $first, 'ends_on' => $last,
                'eligibility_cutoff_on' => $starts->subDays(10)->toDateString(), 'issue_opens_on' => $starts->subDays(30)->toDateString(),
                'issue_closes_on' => $last, 'status' => 'planned',
            ], $this->admin, 'billing.exam_period.created');
        }
        foreach (CapstoneFinance::PRINT_SETTINGS as $key => $value) {
            $setting = FinancialPrintSetting::query()->firstOrNew(['academic_year_id' => $this->year->id, 'setting_key' => $key]);
            $save->execute($setting, ['academic_year_id' => $this->year->id, 'setting_key' => $key, 'setting_value' => $value, 'updated_by' => $this->admin->id], $this->admin, 'billing.print_setting.saved');
        }

        $manage = app(ManageFeeScheduleAction::class);
        $plans = app(ManagePaymentPlanOptionAction::class);
        foreach ($this->grades->sortBy('sequence') as $code => $grade) {
            $this->travel($this->clock->addMinutes(4));
            $stage = $grade->school_stage;
            $required = CapstoneFinance::REQUIRED_FEES[$stage] ?? throw new RuntimeException("No fees for {$stage}.");
            $total = array_sum(array_map('floatval', $required));
            if (abs($total - (float) CapstoneFinance::STAGE_TOTALS[$stage]) > 0.001) {
                throw new RuntimeException("{$stage} fees do not add up to the posted total.");
            }

            $schedule = $manage->create(['academic_year_id' => $this->year->id, 'grade_level_id' => $grade->id, 'name' => $grade->name.' - School Fees '.$this->year->code, 'effective_on' => $this->year->starts_on], $this->admin);
            $lines = [];
            foreach ($required as $feeCode => $amount) {
                $lines[] = [$feeCode, $types[$feeCode]->name, $amount, true, true];
            }
            $lines[] = ['BKS', 'Books — '.$grade->name.' (tentative price)', CapstoneFinance::BOOKS[$code], false, true];
            $lines[] = ['PEU', 'PE Uniform — '.$grade->name, CapstoneFinance::PE_UNIFORM[$code], false, false];
            if (isset(CapstoneFinance::GRADUATION[$code])) {
                $lines[] = ['GRF', CapstoneFinance::GRADUATION[$code][0], CapstoneFinance::GRADUATION[$code][1], false, false];
            }
            foreach ($lines as [$feeCode, $description, $amount, $isRequired, $selected]) {
                $save->execute(new FeeScheduleItem, ['fee_schedule_id' => $schedule->id, 'fee_type_id' => $types[$feeCode]->id, 'description' => $description, 'amount' => $amount, 'is_required' => $isRequired, 'is_default_selected' => $selected], $this->admin, 'billing.fee_schedule_item.created');
            }
            $this->travel($this->clock->addMinutes(2));
            $this->schedules[$code] = $save->execute($schedule, ['status' => 'published', 'published_by' => $this->admin->id, 'published_at' => now()], $this->admin, 'billing.fee_schedule.published');

            foreach (CapstoneFinance::PLAN_OPTIONS as $planCode => $planName) {
                $plans->create(['academic_year_id' => $this->year->id, 'grade_level_id' => $grade->id, 'code' => $planCode, 'name' => $planName, 'is_active' => true], $this->admin);
            }
        }
    }

    private function setPeriodStatus(string $code, string $status): void
    {
        $this->periods[$code] = app(SaveBillingConfigurationAction::class)->execute($this->periods[$code]->fresh(), ['status' => $status], $this->admin, 'billing.exam_period.updated');
    }

    // ---------------------------------------------------------------- teachers and class programs (June 1-3)

    private function assignTeachers(): void
    {
        $create = app(CreateTeachingAssignmentAction::class);
        $from = $this->year->starts_on->toDateString();

        foreach ($this->teachers as $sectionKey => $teacher) {
            $this->travel($this->clock->addMinutes(3));
            $create->execute(['staff_profile_id' => $teacher['staff']->id, 'section_id' => $this->sections[$sectionKey]->id, 'section_subject_id' => null, 'assignment_type' => 'adviser', 'active_from' => $from, 'is_active' => true], $this->admin);
        }

        $plan = $this->planSubjectTeachers();
        $this->travel('2026-06-02 08:45');
        foreach ($plan as $index => [$sectionKey, $sectionSubject]) {
            if ($index > 0 && $index % 12 === 0) {
                $this->travel($this->clock->addMinutes(25));
            }
            $this->travel($this->clock->addMinutes(1));
            $create->execute(['staff_profile_id' => $this->teachers[$sectionKey]['staff']->id, 'section_id' => $sectionSubject->section_id, 'section_subject_id' => $sectionSubject->id, 'assignment_type' => 'subject_teacher', 'active_from' => $from, 'is_active' => true], $this->admin);
        }
    }

    /**
     * Conflict-free subject loads: Grades 1-3 advisers teach their own class; Grades 4-10 are
     * departmentalized by specialty, and preschool advisers take subjects outside their batch hours.
     *
     * @return list<array{0: string, 1: SectionSubject}> [teacher's advised section key, subject offering]
     */
    private function planSubjectTeachers(): array
    {
        $offerings = SectionSubject::query()->with(['subject', 'section.gradeLevel'])
            ->whereIn('section_id', $this->sections->pluck('id'))->where('status', 'active')->get();
        $plan = [];
        $busy = [];
        $load = [];
        foreach ($this->teachers as $key => $teacher) {
            $busy[$key] = [];
            $load[$key] = 0;
            if (isset(self::PRESCHOOL_HOURS[$key])) {
                $busy[$key][] = ClassSchedule::make(ClassSchedule::SCHOOL_WEEK, ...self::PRESCHOOL_HOURS[$key]);
            }
        }
        $assign = function (string $teacherKey, SectionSubject $offering) use (&$busy, &$load, &$plan): void {
            $plan[] = [$teacherKey, $offering];
            $busy[$teacherKey][] = ClassSchedule::fromRecord($offering);
            $load[$teacherKey]++;
        };
        $free = function (string $teacherKey, SectionSubject $offering) use (&$busy): bool {
            $schedule = ClassSchedule::fromRecord($offering);
            foreach ($busy[$teacherKey] as $taken) {
                if ($schedule !== null && $taken !== null && $schedule->overlaps($taken)) {
                    return false;
                }
            }

            return true;
        };

        $sectionKey = fn (SectionSubject $offering) => $offering->section->gradeLevel->code.'.'.$offering->section->name;
        [$selfContained, $departmentalized] = $offerings->partition(fn (SectionSubject $offering) => in_array($offering->section->gradeLevel->code, ['G1', 'G2', 'G3'], true));
        foreach ($selfContained->sortBy(fn ($offering) => $sectionKey($offering).$offering->starts_at) as $offering) {
            $assign($sectionKey($offering), $offering);
        }

        $ordered = $departmentalized->sortBy(fn (SectionSubject $offering) => sprintf('%02d-%s-%s', 20 - $offering->section->gradeLevel->sequence, $offering->subject->code, $offering->section->name));
        foreach ($ordered as $offering) {
            $candidates = collect(array_keys($this->teachers))
                ->filter(fn (string $key) => $load[$key] < 8 && $free($key, $offering))
                ->sortByDesc(function (string $key) use ($offering, $sectionKey, $load): int {
                    $data = $this->teachers[$key]['data'];
                    $score = in_array($offering->subject->code, $data['subjects'], true) ? 40 : 0;
                    $score += $key === $sectionKey($offering) ? 15 : 0;
                    $score += in_array($data['grade'], ['G4', 'G5', 'G6', 'G7', 'G8', 'G9', 'G10'], true) ? 6 : 0;

                    return $score - ($load[$key] * 4);
                });
            $chosen = $candidates->first();
            if ($chosen === null) {
                $this->command?->warn("No free teacher for {$offering->subject->code} in {$sectionKey($offering)}; left unassigned.");

                continue;
            }
            $assign($chosen, $offering);
        }

        return $plan;
    }

    // ---------------------------------------------------------------- learners

    /** @param array<string, mixed> $learner */
    private function scheduleEnrolledLearner(array $learner): void
    {
        $key = $learner['key'];
        $walkIn = CarbonImmutable::parse($learner['walk_in'], self::TIMEZONE);
        $this->state[$key] = ['learner' => $learner];

        $this->at($walkIn, fn () => $this->issueWalkIn($learner));
        $firstLogin = $walkIn->setTime(18, 30)->addMinutes($this->pick($key.'login', 150));
        $this->at($firstLogin, fn () => $this->firstSignIn($key));
        $completed = $firstLogin->addMinutes(20 + $this->pick($key.'form', 35));
        $this->at($completed, fn () => $this->completeApplication($key));

        $review = $this->nextBusinessDay($completed, '08:'.str_pad((string) (35 + $this->pick($key.'review', 20)), 2, '0', STR_PAD_LEFT));
        $this->at($review, fn () => $this->reviewApplication($key));

        if (($learner['category'] ?? 'regular') === 'support_review') {
            $this->at($review->addMinutes(50), fn () => $this->openSupportCase($key));
            $decided = $this->nextBusinessDay($this->nextBusinessDay($review, '13:00'), '14:10');
            $this->at($decided, fn () => $this->decideSupportCase($key));
            $approval = $decided->addMinutes(45);
        } else {
            $approval = $review->addMinutes(35 + $this->pick($key.'approve', 40));
        }
        $this->at($approval, fn () => $this->approveAndPlace($key));
        $this->at($approval->addMinutes(30 + $this->pick($key.'cashier', 30)), fn () => $this->assessAndCollect($key));
    }

    /** @param array<string, mixed> $learner */
    private function schedulePendingLearner(array $learner): void
    {
        $key = $learner['key'];
        $walkIn = CarbonImmutable::parse($learner['walk_in'], self::TIMEZONE);
        $this->state[$key] = ['learner' => $learner];
        $this->at($walkIn, fn () => $this->issueWalkIn($learner));
        $this->at($walkIn->setTime(19, 40), fn () => $this->firstSignIn($key));
        if ($learner['stage'] === 'draft') {
            return;
        }
        $this->at($walkIn->setTime(20, 15), fn () => $this->completeApplication($key));
        $this->at($this->nextBusinessDay($walkIn, '09:20'), fn () => $this->reviewApplication($key, missing: ['FORM_137']));
    }

    /** @param array<string, mixed> $learner */
    private function issueWalkIn(array $learner): void
    {
        $grade = $this->grades[$learner['grade']];
        $guardian = $this->guardianPerson($learner);
        $this->suffix->pin($learner['student_number'] ?? null);
        $issued = app(IssueWalkInAccessAction::class)->execute([
            'academic_year_id' => $this->year->id,
            'grade_level_id' => $grade->id,
            'student_category' => $learner['category'] ?? 'regular',
            'student_first_name' => $learner['first'],
            'student_middle_name' => $learner['middle'],
            'student_last_name' => $learner['last'],
            'extension_name' => null,
            'sex' => $learner['sex'],
            'birth_date' => $learner['birth_date'],
            'lrn' => $learner['lrn'],
            'guardian_first_name' => $guardian['first'],
            'guardian_middle_name' => $guardian['middle'],
            'guardian_last_name' => $guardian['last'],
            'guardian_relationship' => $learner['guardian'] ?? 'mother',
            'guardian_mobile' => null,
            'guardian_email' => $guardian['email'],
        ], $this->registrar);

        $this->state[$learner['key']]['enrollment'] = $issued->enrollment;
        $this->state[$learner['key']]['student_number'] = $issued->studentNumber;
    }

    /** The family changes the temporary password at first sign-in (to the shared demo password). */
    private function firstSignIn(string $key): void
    {
        $owner = $this->state[$key]['enrollment']->fresh()->accessOwner;
        $owner->forceFill([
            'password' => self::PASSWORD,
            'must_change_password' => false,
            'password_changed_at' => now(),
            'temporary_access_expires_at' => null,
            'temporary_access_used_at' => null,
            'last_login_at' => now()->subMinutes(2),
        ])->save();
        app(AuditRecorder::class)->record($owner, 'identity.password.changed', $owner);
        $learner = $this->state[$key]['learner'];
        $this->state[$key]['owner'] = $owner;
        $this->logins[] = [$owner->hasRole('student') ? 'Student' : 'Parent/Guardian', $owner->name, $this->state[$key]['student_number'],
            $this->grades[$learner['grade']]->name.(isset($learner['section']) ? ' '.$learner['section'] : '').(isset($learner['stage']) ? ' (application '.str_replace('_', ' ', $learner['stage']).')' : '')];
    }

    private function completeApplication(string $key): void
    {
        $learner = $this->state[$key]['learner'];
        $enrollment = $this->state[$key]['enrollment']->fresh(['student', 'guardian']);
        $owner = $this->state[$key]['owner'];
        $guardian = $this->guardianPerson($learner);
        $isDisabled = isset($learner['disability']);

        app(SaveEnrollmentDraftAction::class)->execute($enrollment, [
            'lrn' => $learner['lrn'],
            'student_first_name' => $learner['first'],
            'student_middle_name' => $learner['middle'],
            'student_last_name' => $learner['last'],
            'extension_name' => null,
            'sex' => $learner['sex'],
            'birth_date' => $learner['birth_date'],
            'birth_place' => $learner['birth_place'],
            'mother_tongue' => $learner['mother_tongue'],
            'religion' => 'Christianity',
            'ip_group' => null,
            'is_4ps_recipient' => isset($learner['four_ps']),
            'four_ps_household_id' => $learner['four_ps'] ?? null,
            'is_learner_with_disability' => $isDisabled,
            'disability_type' => $learner['disability'] ?? null,
            'current_address' => $learner['address'],
            'same_as_current_address' => true,
            'permanent_address' => null,
            'father_first_name' => $learner['father'][0],
            'father_middle_name' => $learner['father'][1],
            'father_last_name' => $learner['father'][2],
            'mother_first_name' => $learner['mother'][0],
            'mother_middle_name' => $learner['mother'][1],
            'mother_maiden_last_name' => $learner['mother'][2],
            'guardian_first_name' => $guardian['first'],
            'guardian_middle_name' => $guardian['middle'],
            'guardian_last_name' => $guardian['last'],
            'guardian_relationship' => $learner['guardian'] ?? 'mother',
            'guardian_mobile' => null,
            'guardian_email' => $guardian['email'],
            'student_mobile' => null,
            'guardian_sms_opt_in' => false,
            'student_sms_opt_in' => false,
            'academic_year_id' => $this->year->id,
            'grade_level_id' => $enrollment->grade_level_id,
            'student_category' => $learner['category'] ?? 'regular',
            'previous_school_name' => $learner['previous_school'][0] ?? null,
            'previous_school_address' => $learner['previous_school'][1] ?? null,
        ], $owner);

        $this->travel($this->clock->addMinutes(3));
        app(TransitionEnrollmentAction::class)->execute($enrollment->fresh(), 'submitted', $owner);
    }

    /** @param list<string> $missing requirement codes the family has not brought yet */
    private function reviewApplication(string $key, array $missing = []): void
    {
        $enrollment = $this->state[$key]['enrollment']->fresh();
        app(TransitionEnrollmentAction::class)->execute($enrollment, 'under_review', $this->registrar);

        $requirements = $enrollment->requirements()->with('documentType')->get();
        foreach ($requirements as $requirement) {
            $code = $requirement->documentType?->code;
            if (in_array($code, $missing, true)) {
                continue;
            }
            $this->travel($this->clock->addMinutes(2));
            $note = match ($code) {
                'BIRTH_CERTIFICATE' => 'PSA copy presented; photocopy kept on file.',
                'FORM_138' => 'Original SF9 received.',
                'FORM_137' => 'Received sealed from the previous school.',
                'CERTIFICATE_LOV' => 'Certificate presented by the parent; copy kept in the restricted folder.',
                default => 'Hard copy received.',
            };
            $received = app(RecordHardCopyReceiptAction::class)->execute($requirement, true, $this->registrar, $note);
            app(ReviewRequirementAction::class)->execute($received, 'verified', $this->registrar, null);
        }
    }

    private function openSupportCase(string $key): void
    {
        $support = $this->state[$key]['learner']['support'];
        $case = app(CreateSupportCaseAction::class)->execute($this->state[$key]['enrollment']->fresh(), $this->registrar);
        $this->travel($this->clock->addMinutes(4));
        $case = app(ManageSupportCaseAction::class)->assign($case, $this->registrar, $this->admin);
        $this->travel($this->clock->addMinutes(20));
        app(ManageSupportCaseAction::class)->addObservation($case, $support['observation'], $this->registrar);
        $this->state[$key]['support_case'] = $case;
    }

    private function decideSupportCase(string $key): void
    {
        $support = $this->state[$key]['learner']['support'];
        $manage = app(ManageSupportCaseAction::class);
        $case = $manage->recordAssessment($this->state[$key]['support_case']->fresh(), ['outcome' => 'approved', 'confidential_rationale' => $support['rationale'], 'next_action' => $support['next_action']], $this->registrar);
        $this->travel($this->clock->addMinutes(15));
        $manage->approveSummary($case, ['summary' => $support['summary'], 'effective_from' => $this->year->starts_on->toDateString(), 'effective_until' => $this->year->ends_on->toDateString()], $this->admin);
    }

    private function approveAndPlace(string $key): void
    {
        $learner = $this->state[$key]['learner'];
        $enrollment = app(TransitionEnrollmentAction::class)->execute($this->state[$key]['enrollment']->fresh(), 'approved', $this->registrar);
        $this->travel($this->clock->addMinutes(8));
        app(ConfirmSectionAssignmentAction::class)->execute($enrollment->fresh(), $this->sections[$learner['grade'].'.'.$learner['section']], $this->registrar, ($learner['category'] ?? 'regular') === 'support_review');
    }

    // ---------------------------------------------------------------- billing

    private function assessAndCollect(string $key): void
    {
        $learner = $this->state[$key]['learner'];
        $enrollment = $this->state[$key]['enrollment']->fresh();
        $schedule = $this->schedules[$learner['grade']];
        $selected = $schedule->items()->with('feeType')->get()
            ->filter(fn (FeeScheduleItem $item) => ! $item->is_required && in_array($item->feeType->code, $learner['extras'], true))
            ->pluck('id')->all();

        $assessment = app(CreateAssessmentAction::class)->execute($enrollment, $schedule, $selected, $this->accounting);
        $this->travel($this->clock->addMinutes(3));
        $assessment = app(FinalizeAssessmentAction::class)->execute($assessment, $this->accounting);
        $this->travel($this->clock->addMinutes(2));
        $plan = app(SelectPaymentPlanAction::class)->execute($assessment, $learner['plan'], $this->accounting);
        $this->state[$key]['assessment'] = $assessment;

        $enrolledOn = $this->clock;
        $payments = $this->paymentsFor($key, $plan->scheduleItems->map(fn ($row) => ['due_on' => CarbonImmutable::parse($row->due_on->toDateString(), self::TIMEZONE), 'amount' => (string) $row->amount])->all(), $enrolledOn, (string) $assessment->fresh()->net_total);
        foreach ($payments as [$when, $amount]) {
            $this->at($when, fn () => $this->recordPayment($key, $amount));
        }
    }

    /**
     * When the family pays, from its plan's due dates and how it keeps up.
     *
     * @param  list<array{due_on: CarbonImmutable, amount: string}>  $rows
     * @return list<array{0: CarbonImmutable, 1: string}>
     */
    private function paymentsFor(string $key, array $rows, CarbonImmutable $enrolledOn, string $netTotal): array
    {
        $learner = $this->state[$key]['learner'];
        $pays = $learner['pays'];
        $atCounter = $enrolledOn->addMinutes(5);
        if ($pays === 'full') {
            return [[$atCounter, $netTotal]];
        }

        if ($pays === 'behind') {
            // A down payment at the counter, then small amounts that never catch up with what is due.
            return [
                [$atCounter, '3000.00'],
                [CarbonImmutable::parse('2026-08-14 10:20', self::TIMEZONE)->addMinutes($this->pick($key, 120)), '1000.00'],
                [CarbonImmutable::parse('2026-09-25 13:10', self::TIMEZONE)->addMinutes($this->pick($key.'sep', 120)), '1500.00'],
            ];
        }

        $lastDue = match ($pays) {
            'ontime' => CarbonImmutable::parse('2026-10-09', self::TIMEZONE),
            default => CarbonImmutable::parse('2026-08-31', self::TIMEZONE), // q1only
        };
        $payments = [];
        $carry = 0;
        foreach ($rows as $index => $row) {
            if ($row['due_on']->gt($lastDue)) {
                break;
            }
            $cents = (int) round(((float) $row['amount']) * 100);
            // Quarterly families leave a down payment on the enrollment day and settle the rest of the first installment before it is due.
            if ($index === 0 && $learner['plan'] === 'quarterly' && $row['due_on']->gt($atCounter)) {
                $down = 300000;
                $payments[] = [$atCounter, number_format($down / 100, 2, '.', '')];
                $cents -= $down;
            }
            if ($row['due_on']->lte($atCounter)) {
                $carry += $cents; // already due when they enrolled: paid together at the counter

                continue;
            }
            $day = $this->businessDayOnOrBefore($row['due_on']->subDays($this->pick($key.$index, 5)));
            $day = $day->min($this->latest->startOfDay());
            $day = $day->setTime(8 + $this->pick($key.'h'.$index, 8), 5 + $this->pick($key.'m'.$index, 50));
            $payments[] = [$day->lt($atCounter) ? $atCounter : $day, number_format($cents / 100, 2, '.', '')];
        }
        if ($carry > 0) {
            array_unshift($payments, [$atCounter, number_format($carry / 100, 2, '.', '')]);
        }

        return $payments;
    }

    private function recordPayment(string $key, string $amount): void
    {
        $learner = $this->state[$key]['learner'];
        $assessment = $this->state[$key]['assessment']->fresh();
        $guardian = $this->guardianPerson($learner);
        $method = $learner['method'];
        if ($method !== 'cash' && (float) $amount < 2000) {
            $method = 'cash';
        }
        $this->referenceCounter++;
        $data = [
            'received_on' => $this->clock->toDateString(),
            'amount' => $amount,
            'method' => $method,
            'payer_name' => $guardian['first'].' '.$guardian['last'],
        ];
        if ($method === 'e_wallet') {
            $data['method_reference'] = '10'.str_pad((string) (28431 * $this->referenceCounter + 7019), 11, '0', STR_PAD_LEFT);
            $data['method_description'] = null;
        } elseif ($method === 'bank_transfer') {
            $bank = $this->pick($key, 2) === 0 ? ['BDO', 'BDO Unibank'] : ['LBP', 'Land Bank of the Philippines'];
            $data['method_reference'] = $bank[0].'-'.$this->clock->format('ymd').'-'.str_pad((string) (4100 + $this->referenceCounter * 37), 5, '0', STR_PAD_LEFT);
            $data['bank_name'] = $bank[1];
        }
        $payment = app(CreateDraftPaymentAction::class)->execute($assessment, $data, $this->accounting);
        $this->travel($this->clock->addMinutes(1));
        app(PostPaymentAction::class)->execute($payment, $this->accounting);
    }

    /** @param list<string> $days */
    private function scheduleExamPermits(string $periodCode, array $days): void
    {
        foreach (CapstoneRoster::LEARNERS as $learner) {
            if (! NumericGradeLevels::includes($learner['grade'])) {
                continue; // preschool has no quarterly examination permits
            }
            $day = CarbonImmutable::parse($days[$this->pick($learner['key'].$periodCode, count($days))], self::TIMEZONE)
                ->setTime(9 + $this->pick($learner['key'].$periodCode.'h', 6), 5 + $this->pick($learner['key'].$periodCode.'m', 50));
            if ($periodCode === 'Q2' && $this->pick($learner['key'].'q2visit', 10) >= 6) {
                continue; // the Q2 exam is still two weeks away; not every family has come for the permit yet
            }
            $this->at($day, function () use ($learner, $periodCode): void {
                $assessment = $this->state[$learner['key']]['assessment'] ?? null;
                if (! $assessment instanceof FeeAssessment) {
                    return;
                }
                $assessment = $assessment->fresh(['enrollment', 'plan.scheduleItems']);
                $period = $this->periods[$periodCode]->fresh();
                if (! app(PermitEligibilityCalculator::class)->calculate($assessment, $period)['eligible']) {
                    return; // not settled yet: Accounting does not issue the permit
                }
                app(IssueExamPermitAction::class)->execute($assessment, $period, $this->accounting);
            });
        }
    }

    // ---------------------------------------------------------------- grades

    private function scheduleFirstQuarterGrades(): void
    {
        $days = ['2026-08-26', '2026-08-27', '2026-08-28', '2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04'];
        $this->at('2026-08-25 16:00', function () use ($days): void {
            $assignments = TeachingAssignment::query()->with(['section.gradeLevel', 'sectionSubject.subject', 'staffProfile.user'])
                ->where('assignment_type', 'subject_teacher')->where('is_active', true)
                ->whereIn('section_id', $this->sections->pluck('id'))->get()
                ->filter(fn (TeachingAssignment $assignment) => NumericGradeLevels::includes($assignment->section->gradeLevel));
            foreach ($assignments as $assignment) {
                $seed = $assignment->section->name.$assignment->sectionSubject->subject->code;
                $when = CarbonImmutable::parse($days[$this->pick($seed, count($days))], self::TIMEZONE)->setTime(15 + $this->pick($seed.'h', 4), 5 + $this->pick($seed.'m', 50));
                $this->at($when, fn () => $this->encodeFirstQuarter($assignment));
            }
        });
    }

    private function encodeFirstQuarter(TeachingAssignment $assignment): void
    {
        $teacher = $assignment->staffProfile->user;
        $enrollments = Enrollment::query()->where('status', 'enrolled')
            ->whereHas('sectionAssignments', fn ($query) => $query->where('section_id', $assignment->section_id)->where('status', 'confirmed')->whereNull('ended_at'))
            ->with('student')->get();
        foreach ($enrollments as $enrollment) {
            $learnerKey = collect($this->state)->search(fn (array $state) => ($state['enrollment'] ?? null)?->id === $enrollment->id);
            $base = 80 + $this->pick((string) $learnerKey, 15);
            $grade = $base + $this->pick($learnerKey.$assignment->sectionSubject->subject->code, 9) - 4;
            app(SaveQuarterGradesAction::class)->execute($assignment, $enrollment, $teacher, ['quarter_1' => max(76, min(98, $grade))]);
        }
    }

    // ---------------------------------------------------------------- people helpers

    /**
     * The parent or guardian named on the application (and, for Nursery-Grade 6, at the counter).
     *
     * @param  array<string, mixed>  $learner
     * @return array{first: string, middle: ?string, last: string, email: ?string}
     */
    private function guardianPerson(array $learner): array
    {
        if (! isset($learner['father'])) {
            return ['first' => 'Rosario', 'middle' => null, 'last' => $learner['last'], 'email' => null];
        }
        [$first, $middle, $last] = $learner['guardian_person'] ?? match ($learner['guardian'] ?? 'mother') {
            'father' => $learner['father'],
            // A married mother's middle name is her maiden surname.
            default => [$learner['mother'][0], $learner['mother'][2], $learner['last']],
        };
        $email = Str::lower(Str::of($first)->before(' ')->ascii().'.'.Str::of($last)->replace(' ', '')->ascii()).'@'.CapstoneRoster::EMAIL_DOMAIN;

        return ['first' => $first, 'middle' => $middle, 'last' => $last, 'email' => $email];
    }

    /** @return array<string, mixed>|null */
    private function readSnapshot(): ?array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::SNAPSHOT_PATH)) {
            $this->command?->warn('No staff snapshot found; creating Maria Dela Cruz (Admin), Jenny Santos (Registrar), and Angelica Garcia (Accounting).');

            return null;
        }
        $data = json_decode((string) $disk->get(self::SNAPSHOT_PATH), true);
        if (! is_array($data) || ! is_array($data['accounts'] ?? null)) {
            throw new RuntimeException('The staff snapshot at storage/app/private/'.self::SNAPSHOT_PATH.' is unreadable. Delete it or take it again.');
        }

        return $data;
    }

    private function report(): void
    {
        $enrolled = Enrollment::query()->where('status', 'enrolled')->count();
        $this->command?->info("Capstone school year ready: {$enrolled} enrolled learners, ".count(CapstoneRoster::PENDING).' applications in progress, '.count(CapstoneRoster::TEACHERS).' teachers.');
        $this->command?->info('Every account below signs in with its Staff ID or Student ID and the password '.self::PASSWORD.'.');
        $this->command?->table(['Role', 'Name', 'Sign-in ID', 'Email / class'], $this->logins);
    }
}
