<?php

namespace Database\Seeders\Billing;

use App\Models\User;
use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Billing\Models\DiscountCampaign;
use App\Modules\Billing\Models\ExamPeriod;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Billing\Models\FeeScheduleItem;
use App\Modules\Billing\Models\FeeType;
use App\Modules\Billing\Models\FinancialPrintSetting;
use App\Modules\Billing\Models\PaymentPlanOption;
use App\Modules\Billing\Models\SchoolNonBusinessDay;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CurrentSchoolYearJuniorHighBillingTestSeeder extends Seeder
{
    private int $createdRecords = 0;

    public function run(): void
    {
        DB::transaction(function (): void {
            $year = AcademicYear::query()
                ->where('code', '2026-2027')
                ->where('status', 'open')
                ->lockForUpdate()
                ->firstOrFail();
            $actor = User::query()
                ->where('status', 'active')
                ->whereHas('roles', fn ($query) => $query->where('code', 'admin'))
                ->orderBy('id')
                ->firstOrFail();
            $grades = GradeLevel::query()
                ->where('school_stage', 'Junior High School')
                ->where('is_active', true)
                ->whereBetween('sequence', [7, 10])
                ->orderBy('sequence')
                ->lockForUpdate()
                ->get();

            if ($grades->isEmpty()) {
                throw new RuntimeException('No active Junior High School grade levels are configured.');
            }

            $types = $this->feeTypes($actor);

            foreach ($grades as $grade) {
                $this->feeSchedule($year, $grade, $types, $actor);
                $this->paymentPlans($year, $grade, $actor);
            }

            $this->examPeriods($year, $actor);
            $this->nonBusinessDays($year, $actor);
            $this->inactiveDiscountCampaign($year, $actor);
            $this->printSettings($year, $actor);

            app(AuditRecorder::class)->record($actor, 'billing.development_jhs_configuration.seeded', $year, [
                'created_records' => $this->createdRecords,
                'grade_count' => $grades->count(),
                'school_year_code' => $year->code,
            ]);
        });
    }

    /** @return array<string, FeeType> */
    private function feeTypes(User $actor): array
    {
        $definitions = [
            ['code' => 'TUI', 'name' => 'Tuition Fee', 'category' => 'Tuition', 'is_mandatory' => true, 'is_conditional' => false, 'is_permit_critical' => true, 'max_quantity' => 1],
            ['code' => 'MSC', 'name' => 'Miscellaneous Fee', 'category' => 'School Fee', 'is_mandatory' => true, 'is_conditional' => false, 'is_permit_critical' => true, 'max_quantity' => 1],
            ['code' => 'BKS', 'name' => 'Books', 'category' => 'Learning Materials', 'is_mandatory' => false, 'is_conditional' => true, 'is_permit_critical' => false, 'max_quantity' => 1],
            ['code' => 'PEU', 'name' => 'PE Uniform', 'category' => 'Uniform', 'is_mandatory' => false, 'is_conditional' => false, 'is_permit_critical' => false, 'max_quantity' => 1],
            ['code' => 'LGT', 'name' => 'Logo/Tela', 'category' => 'Uniform', 'is_mandatory' => false, 'is_conditional' => false, 'is_permit_critical' => false, 'max_quantity' => 1],
            ['code' => 'BIB', 'name' => 'Bible', 'category' => 'Learning Materials', 'is_mandatory' => false, 'is_conditional' => true, 'is_permit_critical' => false, 'max_quantity' => 1],
            ['code' => 'GRF', 'name' => 'Graduation/Recognition Fee', 'category' => 'Activity', 'is_mandatory' => false, 'is_conditional' => true, 'is_permit_critical' => false, 'max_quantity' => 1],
        ];
        $types = [];

        foreach ($definitions as $definition) {
            $type = FeeType::query()->firstOrCreate(['code' => $definition['code']], [...$definition, 'is_active' => true]);
            $this->auditCreated($type, $actor, 'billing.development_fee_type.created');
            $types[$definition['code']] = $type;
        }

        return $types;
    }

    /** @param array<string, FeeType> $types */
    private function feeSchedule(AcademicYear $year, GradeLevel $grade, array $types, User $actor): void
    {
        $name = 'DEVELOPMENT TEST - '.$grade->name.' JHS SY 2026-2027';
        $schedule = FeeSchedule::query()
            ->where('academic_year_id', $year->id)
            ->where('grade_level_id', $grade->id)
            ->where('name', $name)
            ->first();

        if ($schedule === null) {
            $schedule = FeeSchedule::query()->create([
                'academic_year_id' => $year->id,
                'grade_level_id' => $grade->id,
                'name' => $name,
                'version' => ((int) FeeSchedule::query()
                    ->where('academic_year_id', $year->id)
                    ->where('grade_level_id', $grade->id)
                    ->max('version')) + 1,
                'status' => 'draft',
                'effective_on' => $year->starts_on,
            ]);
            $this->auditCreated($schedule, $actor, 'billing.development_fee_schedule.created');
        }

        if ($schedule->status === 'published') {
            return;
        }

        if ($schedule->status !== 'draft') {
            throw new RuntimeException("The development schedule for {$grade->name} is not editable.");
        }

        $amounts = [
            'TUI' => ['27000.00', true, true],
            'MSC' => ['5500.00', true, true],
            'BKS' => ['5500.00', false, false],
            'PEU' => ['900.00', false, false],
            'LGT' => ['800.00', false, false],
            'BIB' => ['650.00', false, false],
        ];

        foreach ($amounts as $code => [$amount, $required, $defaultSelected]) {
            $item = FeeScheduleItem::query()->firstOrCreate([
                'fee_schedule_id' => $schedule->id,
                'fee_type_id' => $types[$code]->id,
            ], [
                'description' => $types[$code]->name,
                'amount' => $amount,
                'is_required' => $required,
                'is_default_selected' => $defaultSelected,
            ]);
            $this->auditCreated($item, $actor, 'billing.development_fee_schedule_item.created');
        }

        $schedule->update([
            'status' => 'published',
            'published_by' => $actor->id,
            'published_at' => now(),
        ]);
        app(AuditRecorder::class)->record($actor, 'billing.development_fee_schedule.published', $schedule);
    }

    private function paymentPlans(AcademicYear $year, GradeLevel $grade, User $actor): void
    {
        foreach ([
            'full' => 'DEVELOPMENT TEST - Full Payment',
            'semiannual' => 'DEVELOPMENT TEST - Semiannual',
            'quarterly' => 'DEVELOPMENT TEST - Quarterly',
            'monthly' => 'DEVELOPMENT TEST - Monthly',
        ] as $code => $name) {
            $option = PaymentPlanOption::query()->firstOrCreate([
                'academic_year_id' => $year->id,
                'grade_level_id' => $grade->id,
                'code' => $code,
            ], [
                'name' => $name,
                'is_active' => true,
            ]);
            $this->auditCreated($option, $actor, 'billing.development_payment_plan.created');
        }
    }

    private function examPeriods(AcademicYear $year, User $actor): void
    {
        foreach ([
            ['DEV-JHS-Q1', 'DEVELOPMENT TEST - First Quarterly Examination', '2026-08-20', '2026-08-21'],
            ['DEV-JHS-Q2', 'DEVELOPMENT TEST - Second Quarterly Examination', '2026-10-22', '2026-10-23'],
            ['DEV-JHS-Q3', 'DEVELOPMENT TEST - Third Quarterly Examination', '2027-01-21', '2027-01-22'],
            ['DEV-JHS-Q4', 'DEVELOPMENT TEST - Fourth Quarterly Examination', '2027-03-18', '2027-03-19'],
        ] as [$code, $name, $startsOn, $endsOn]) {
            $period = ExamPeriod::query()->firstOrCreate([
                'academic_year_id' => $year->id,
                'code' => $code,
            ], [
                'name' => $name,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'eligibility_cutoff_on' => date('Y-m-d', strtotime($startsOn.' -10 days')),
                'issue_opens_on' => date('Y-m-d', strtotime($startsOn.' -30 days')),
                'issue_closes_on' => $endsOn,
                'status' => 'planned',
            ]);
            $this->auditCreated($period, $actor, 'billing.development_exam_period.created');
        }
    }

    private function nonBusinessDays(AcademicYear $year, User $actor): void
    {
        foreach ([
            '2026-08-31' => 'DEVELOPMENT TEST - School non-business day',
            '2026-12-24' => 'DEVELOPMENT TEST - Christmas break',
        ] as $date => $label) {
            $day = SchoolNonBusinessDay::query()->firstOrCreate([
                'academic_year_id' => $year->id,
                'calendar_date' => $date,
            ], ['label' => $label]);
            $this->auditCreated($day, $actor, 'billing.development_non_business_day.created');
        }
    }

    private function inactiveDiscountCampaign(AcademicYear $year, User $actor): void
    {
        $campaign = DiscountCampaign::query()->firstOrCreate([
            'academic_year_id' => $year->id,
            'code' => 'DEV-JHS-FIRST-100',
        ], [
            'name' => 'DEVELOPMENT TEST - JHS First 100 Early Enrollees',
            'value_type' => 'fixed',
            'value_amount' => '1000.00',
            'slot_limit' => 100,
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-05-31',
            'is_active' => false,
            'is_development_fixture' => true,
        ]);
        $this->auditCreated($campaign, $actor, 'billing.development_discount_campaign.created');
    }

    private function printSettings(AcademicYear $year, User $actor): void
    {
        foreach ([
            'receipt_signatory_role' => 'Authorized Accounting Staff (DEVELOPMENT TEST)',
            'permit_signatory_role' => 'School Administrator (DEVELOPMENT TEST)',
            'receipt_footer' => 'DEVELOPMENT TEST - Not an official receipt.',
            'permit_footer' => 'DEVELOPMENT TEST - Not an official examination permit.',
        ] as $key => $value) {
            $setting = FinancialPrintSetting::query()->firstOrCreate([
                'academic_year_id' => $year->id,
                'setting_key' => $key,
            ], [
                'setting_value' => $value,
                'updated_by' => $actor->id,
            ]);
            $this->auditCreated($setting, $actor, 'billing.development_print_setting.created');
        }
    }

    private function auditCreated(Model $model, User $actor, string $action): void
    {
        if (! $model->wasRecentlyCreated) {
            return;
        }

        $this->createdRecords++;
        app(AuditRecorder::class)->record($actor, $action, $model);
    }
}
