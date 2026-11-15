<?php

namespace Database\Seeders\Enrollment;

use App\Models\User;
use App\Modules\Academics\Actions\CreateTeachingAssignmentAction;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Billing\Actions\CreateAssessmentAction;
use App\Modules\Billing\Actions\CreateDraftPaymentAction;
use App\Modules\Billing\Actions\FinalizeAssessmentAction;
use App\Modules\Billing\Actions\ManageFeeScheduleAction;
use App\Modules\Billing\Actions\ManagePaymentPlanOptionAction;
use App\Modules\Billing\Actions\PostPaymentAction;
use App\Modules\Billing\Actions\SaveBillingConfigurationAction;
use App\Modules\Billing\Actions\SelectPaymentPlanAction;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Billing\Models\FeeScheduleItem;
use App\Modules\Billing\Models\FeeType;
use App\Modules\Billing\Models\PaymentPlanOption;
use App\Modules\Enrollment\Actions\IssueWalkInAccessAction;
use App\Modules\Enrollment\Actions\SaveEnrollmentDraftAction;
use App\Modules\Enrollment\Actions\TransitionEnrollmentAction;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Identity\Actions\AssignRolesAction;
use App\Modules\Identity\Actions\AuthenticateUserAction;
use App\Modules\Identity\Actions\ContactNormalizer;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\People\Actions\IssueStaffNumberAction;
use App\Modules\People\Models\StaffProfile;
use App\Modules\People\Support\RandomThreeDigitSuffix;
use App\Modules\Requirements\Actions\RecordHardCopyReceiptAction;
use App\Modules\Requirements\Actions\ReviewRequirementAction;
use App\Modules\SectionAssignment\Actions\ConfirmSectionAssignmentAction;
use App\Modules\Shared\Audit\AuditRecorder;
use Carbon\CarbonImmutable;
use Database\Seeders\Capstone\CapstoneDemoSeeder;
use Database\Seeders\Capstone\CapstoneFinance;
use Database\Seeders\Capstone\CapstoneRoster;
use Database\Seeders\Capstone\PinnedThreeDigitSuffix;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * One Nursery learner enrolled the way a walk-in family does it, on an existing open 2026-2027 year:
 * the Registrar issues walk-in access, the mother signs in with the temporary password and changes it,
 * fills in and submits the application, the Registrar receives the PSA birth certificate, the Admin
 * waives the SF9 (first school year), and the Registrar approves and places the learner in Nursery Hope; Accounting assesses
 * the fees, the mother picks the monthly plan and pays what is already due at the counter.
 *
 * When Nursery Hope has no adviser, its roster teacher (Ma. Lourdes Fernandez, 18-JCA-946) is created
 * and assigned. When Nursery has no published fee schedule or payment plans for the year, they are
 * created from the school's posted Pre-elementary fee sheet (CapstoneFinance). Existing setup is reused.
 *
 * All people are fictional; emails use the non-deliverable gmail.test domain and nobody has a mobile
 * number, so no SMS or mail can reach anyone. Accounts use CapstoneDemoSeeder::PASSWORD.
 *
 * php artisan db:seed --class="Database\Seeders\Enrollment\NurseryWalkInSeeder"
 */
class NurseryWalkInSeeder extends Seeder
{
    private const TIMEZONE = 'Asia/Manila';

    /** Nothing is dated after this moment. */
    public const LATEST = '2026-10-23 10:15';

    private const YEAR_CODE = '2026-2027';

    private const SECTION = 'Hope';

    public const LEARNER = [
        'first' => 'Althea Joy', 'middle' => 'Rivera', 'last' => 'Manalo', 'sex' => 'female', 'birth_date' => '2023-03-14', 'lrn' => null,
        'birth_place' => 'Lingayen, Pangasinan', 'mother_tongue' => 'Pangasinan', 'address' => '14 Purok 3, Poblacion, Lingayen, Pangasinan',
        'father' => ['Ronald', 'Mendoza', 'Manalo'], 'mother' => ['Joanna', 'Castro', 'Rivera'], 'guardian' => 'mother',
        'plan' => 'monthly', 'extras' => ['BKS', 'PEU'],
    ];

    private CarbonImmutable $latest;

    private AcademicYear $year;

    private GradeLevel $nursery;

    private Section $section;

    private User $admin;

    private User $registrar;

    private User $accounting;

    private PinnedThreeDigitSuffix $suffix;

    public function run(): void
    {
        $this->latest = CarbonImmutable::parse(self::LATEST, self::TIMEZONE);
        if (CarbonImmutable::now(self::TIMEZONE)->lt($this->latest)) {
            throw new RuntimeException('The Nursery walk-in is dated up to '.self::LATEST.'; run it on or after that date.');
        }
        $this->loadSchool();
        $this->suffix = new PinnedThreeDigitSuffix;
        app()->instance(RandomThreeDigitSuffix::class, $this->suffix);

        try {
            $summary = DB::transaction(function (): array {
                $this->ensureAdviser();
                $schedule = $this->ensureFeeSchedule();

                return $this->enroll($schedule);
            });
        } finally {
            app()->forgetInstance(RandomThreeDigitSuffix::class);
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }

        $this->command?->info('Nursery walk-in enrolled: '.$summary['learner'].' in Nursery '.self::SECTION.' ('.$summary['status'].').');
        $this->command?->table(['Who', 'Signs in with', 'Detail'], [
            ['Parent/Guardian '.$summary['guardian'], $summary['student_number'], 'Student ID of the learner'],
            ['Adviser '.$summary['adviser'], $summary['adviser_number'], 'Staff ID'],
        ]);
        $this->command?->info('Both use the shared demo password in CapstoneDemoSeeder::PASSWORD.');
    }

    private function travel(string $at): void
    {
        $moment = CarbonImmutable::parse($at, self::TIMEZONE);
        if ($moment->gt($this->latest)) {
            throw new RuntimeException('Nursery walk-in step dated after '.self::LATEST.': '.$at);
        }
        Carbon::setTestNow(Carbon::instance($moment->toDateTime())->setTimezone(config('app.timezone')));
        CarbonImmutable::setTestNow($moment->setTimezone(config('app.timezone')));
    }

    private function loadSchool(): void
    {
        $this->year = AcademicYear::query()->where('code', self::YEAR_CODE)->where('status', 'open')->first()
            ?? throw new RuntimeException('An open '.self::YEAR_CODE.' school year is required.');
        $this->nursery = GradeLevel::query()->where('code', 'NUR')->firstOrFail();
        $this->section = Section::query()->where('academic_year_id', $this->year->id)->where('grade_level_id', $this->nursery->id)
            ->where('name', self::SECTION)->where('status', 'active')->first()
            ?? throw new RuntimeException('Nursery '.self::SECTION.' must exist in '.self::YEAR_CODE.' (publish the class program first).');

        $this->admin = $this->staffWithRole('admin');
        $this->registrar = $this->staffWithRole('registrar');
        $this->accounting = $this->staffWithRole('accounting');
    }

    private function staffWithRole(string $role): User
    {
        return User::query()->where('status', AccountStatus::Active)
            ->whereHas('roles', fn ($query) => $query->where('code', $role))->orderBy('id')->first()
            ?? throw new RuntimeException("An active {$role} account is required.");
    }

    // ---------------------------------------------------------------- setup the Admin finishes first (Oct 21)

    private function ensureAdviser(): void
    {
        $hasAdviser = TeachingAssignment::query()->where('section_id', $this->section->id)
            ->where('assignment_type', 'adviser')->where('is_active', true)->exists();
        if ($hasAdviser) {
            return;
        }

        $teacher = collect(CapstoneRoster::TEACHERS)->firstWhere(fn (array $row) => $row['grade'] === 'NUR' && $row['section'] === self::SECTION);
        $email = Str::lower(Str::of($teacher['first'])->replace(['Ma. ', ' '], ['', ''])->ascii().'.'.Str::of($teacher['last'])->replace(' ', '')->ascii()).'@'.CapstoneRoster::EMAIL_DOMAIN;
        $staff = StaffProfile::query()->whereHas('user', fn ($query) => $query->where('email', $email))->first();

        if ($staff === null) {
            $this->travel('2026-10-21 08:10');
            $user = User::query()->create([
                'name' => $teacher['first'].' '.$teacher['last'],
                'email' => ContactNormalizer::email($email),
                'mobile' => null,
                'password' => CapstoneDemoSeeder::PASSWORD,
                'status' => AccountStatus::Active,
                'must_change_password' => false,
                'password_changed_at' => now(),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            app(AssignRolesAction::class)->execute($user, ['teacher'], $this->admin);
            $staff = StaffProfile::query()->create(['user_id' => $user->id, 'position_title' => 'Teacher', 'active_from' => $teacher['employed_on']]);
            $this->suffix->pin($teacher['staff_number']);
            app(IssueStaffNumberAction::class)->execute($staff, CarbonImmutable::parse($teacher['employed_on']), $this->admin);
            app(AuditRecorder::class)->record($this->admin, 'identity.user.created', $user, ['roles' => 'teacher', 'source' => 'staff_setup']);
        }

        $this->travel('2026-10-21 08:25');
        app(CreateTeachingAssignmentAction::class)->execute([
            'staff_profile_id' => $staff->id, 'section_id' => $this->section->id, 'section_subject_id' => null,
            'assignment_type' => 'adviser', 'active_from' => $this->year->starts_on->toDateString(), 'is_active' => true,
        ], $this->admin);
    }

    private function ensureFeeSchedule(): FeeSchedule
    {
        $schedule = FeeSchedule::query()->where('academic_year_id', $this->year->id)->where('grade_level_id', $this->nursery->id)
            ->where('status', 'published')->latest('id')->first();

        if ($schedule === null) {
            $this->travel('2026-10-21 09:00');
            $types = FeeType::query()->where('is_active', true)->get()->keyBy('code');
            $required = CapstoneFinance::REQUIRED_FEES[$this->nursery->school_stage];
            foreach ([...array_keys($required), 'BKS', 'PEU'] as $code) {
                if (! $types->has($code)) {
                    throw new RuntimeException("Fee type {$code} is missing or inactive; add it under Billing configuration first.");
                }
            }
            $lines = [];
            foreach ($required as $code => $amount) {
                $lines[] = [$code, $types[$code]->name, $amount, true, true];
            }
            $lines[] = ['BKS', 'Books — Nursery (tentative price)', CapstoneFinance::BOOKS['NUR'], false, true];
            $lines[] = ['PEU', 'PE Uniform — Nursery', CapstoneFinance::PE_UNIFORM['NUR'], false, false];

            $save = app(SaveBillingConfigurationAction::class);
            $schedule = app(ManageFeeScheduleAction::class)->create([
                'academic_year_id' => $this->year->id, 'grade_level_id' => $this->nursery->id,
                'name' => 'Nursery - School Fees '.$this->year->code, 'effective_on' => $this->year->starts_on,
            ], $this->admin);
            foreach ($lines as [$code, $description, $amount, $isRequired, $selected]) {
                $save->execute(new FeeScheduleItem, [
                    'fee_schedule_id' => $schedule->id, 'fee_type_id' => $types[$code]->id, 'description' => $description,
                    'amount' => $amount, 'is_required' => $isRequired, 'is_default_selected' => $selected,
                ], $this->admin, 'billing.fee_schedule_item.created');
            }
            $this->travel('2026-10-21 09:06');
            $schedule = $save->execute($schedule, ['status' => 'published', 'published_by' => $this->admin->id, 'published_at' => now()], $this->admin, 'billing.fee_schedule.published');
        }

        $plans = app(ManagePaymentPlanOptionAction::class);
        foreach (CapstoneFinance::PLAN_OPTIONS as $code => $name) {
            $exists = PaymentPlanOption::query()->where('academic_year_id', $this->year->id)->where('grade_level_id', $this->nursery->id)->where('code', $code)->exists();
            if (! $exists) {
                $this->travel('2026-10-21 09:10');
                $plans->create(['academic_year_id' => $this->year->id, 'grade_level_id' => $this->nursery->id, 'code' => $code, 'name' => $name, 'is_active' => true], $this->admin);
            }
        }
        $chosen = PaymentPlanOption::query()->where('academic_year_id', $this->year->id)->where('grade_level_id', $this->nursery->id)->where('code', self::LEARNER['plan'])->sole();
        if (! $chosen->is_active) {
            throw new RuntimeException('The Nursery '.self::LEARNER['plan'].' payment plan is inactive; activate it under Billing configuration first.');
        }

        return $schedule;
    }

    // ---------------------------------------------------------------- the family's enrollment (Oct 22-23)

    /** @return array<string, string> */
    private function enroll(FeeSchedule $schedule): array
    {
        $learner = self::LEARNER;
        [$motherFirst, , $motherMaiden] = $learner['mother'];
        $guardian = ['first' => $motherFirst, 'middle' => $motherMaiden, 'last' => $learner['last']];
        $guardianEmail = Str::lower($guardian['first'].'.'.$guardian['last']).'@'.CapstoneRoster::EMAIL_DOMAIN;

        // Thursday morning: the mother walks in; the Registrar checks the PSA copy and issues access.
        $this->travel('2026-10-22 09:20');
        $issued = app(IssueWalkInAccessAction::class)->execute([
            'academic_year_id' => $this->year->id,
            'grade_level_id' => $this->nursery->id,
            'student_category' => 'regular',
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
            'guardian_relationship' => $learner['guardian'],
            'guardian_mobile' => null,
            'guardian_email' => $guardianEmail,
        ], $this->registrar);
        $enrollment = $issued->enrollment;

        // That evening: first sign-in with the Student ID and temporary password, then a private password.
        $this->travel('2026-10-22 19:05');
        $owner = app(AuthenticateUserAction::class)->execute($issued->studentNumber, $issued->temporaryPassword)
            ?? throw new RuntimeException('The issued temporary password did not sign in.');
        $this->travel('2026-10-22 19:07');
        $owner->forceFill([
            'password' => CapstoneDemoSeeder::PASSWORD,
            'must_change_password' => false,
            'password_changed_at' => now(),
            'temporary_access_expires_at' => null,
            'temporary_access_used_at' => null,
        ])->save();
        app(AuditRecorder::class)->record($owner, 'identity.password.changed', $owner);

        $this->travel('2026-10-22 19:38');
        app(SaveEnrollmentDraftAction::class)->execute($enrollment->fresh(), [
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
            'is_4ps_recipient' => false,
            'four_ps_household_id' => null,
            'is_learner_with_disability' => false,
            'disability_type' => null,
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
            'guardian_relationship' => $learner['guardian'],
            'guardian_mobile' => null,
            'guardian_email' => $guardianEmail,
            'student_mobile' => null,
            'guardian_sms_opt_in' => false,
            'student_sms_opt_in' => false,
            'academic_year_id' => $this->year->id,
            'grade_level_id' => $this->nursery->id,
            'student_category' => 'regular',
            'previous_school_name' => null,
            'previous_school_address' => null,
        ], $owner);
        $this->travel('2026-10-22 19:41');
        app(TransitionEnrollmentAction::class)->execute($enrollment->fresh(), 'submitted', $owner);

        // Friday: the Registrar reviews; Nursery is the learner's first school year, so there is no SF9.
        $this->travel('2026-10-23 08:40');
        app(TransitionEnrollmentAction::class)->execute($enrollment->fresh(), 'under_review', $this->registrar);
        foreach ($enrollment->requirements()->with('documentType')->orderBy('id')->get()->values() as $index => $requirement) {
            $this->travel('2026-10-23 08:'.(42 + min($index, 9) * 2));
            if ($requirement->documentType?->code === 'BIRTH_CERTIFICATE') {
                $received = app(RecordHardCopyReceiptAction::class)->execute($requirement, true, $this->registrar, 'PSA copy presented; photocopy kept on file.');
                app(ReviewRequirementAction::class)->execute($received, 'verified', $this->registrar, null);
            } else {
                // Waivers are Admin-only (REQ-007); the Registrar asks the Admin at the counter.
                app(ReviewRequirementAction::class)->execute($requirement, 'waived', $this->admin, 'First-time Nursery enrollee; no previous school record.');
            }
        }

        $this->travel('2026-10-23 09:20');
        app(TransitionEnrollmentAction::class)->execute($enrollment->fresh(), 'approved', $this->registrar);
        $this->travel('2026-10-23 09:28');
        app(ConfirmSectionAssignmentAction::class)->execute($enrollment->fresh(), $this->section, $this->registrar, false);

        // Accounting assesses the fees; the mother picks the monthly plan and pays what is already due.
        $this->travel('2026-10-23 09:55');
        $extras = $schedule->items()->with('feeType')->get()
            ->filter(fn (FeeScheduleItem $item) => ! $item->is_required && in_array($item->feeType->code, $learner['extras'], true))
            ->pluck('id')->all();
        $assessment = app(CreateAssessmentAction::class)->execute($enrollment->fresh(), $schedule, $extras, $this->accounting);
        $this->travel('2026-10-23 09:58');
        $assessment = app(FinalizeAssessmentAction::class)->execute($assessment, $this->accounting);
        $this->travel('2026-10-23 10:00');
        $plan = app(SelectPaymentPlanAction::class)->execute($assessment, $learner['plan'], $this->accounting);

        $today = now()->toDateString();
        $dueCents = $plan->scheduleItems->filter(fn ($row) => $row->due_on->toDateString() <= $today)
            ->sum(fn ($row) => (int) round(((float) $row->amount) * 100));
        if ($dueCents === 0) {
            $dueCents = (int) round(((float) $plan->scheduleItems->sortBy('due_on')->first()->amount) * 100);
        }
        $this->travel('2026-10-23 10:06');
        $payment = app(CreateDraftPaymentAction::class)->execute($assessment->fresh(), [
            'received_on' => $today,
            'amount' => number_format($dueCents / 100, 2, '.', ''),
            'method' => 'cash',
            'payer_name' => $guardian['first'].' '.$guardian['last'],
        ], $this->accounting);
        $this->travel('2026-10-23 10:07');
        app(PostPaymentAction::class)->execute($payment, $this->accounting);

        $adviser = TeachingAssignment::query()->with('staffProfile.user')->where('section_id', $this->section->id)
            ->where('assignment_type', 'adviser')->where('is_active', true)->first();

        return [
            'learner' => $learner['first'].' '.$learner['middle'].' '.$learner['last'],
            'status' => (string) Enrollment::query()->findOrFail($enrollment->id)->status,
            'guardian' => $guardian['first'].' '.$guardian['middle'].' '.$guardian['last'],
            'student_number' => $issued->studentNumber,
            'adviser' => (string) $adviser?->staffProfile?->user?->name,
            'adviser_number' => (string) $adviser?->staffProfile?->employee_number,
        ];
    }
}
