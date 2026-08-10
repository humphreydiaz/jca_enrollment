<?php

namespace Database\Seeders\Billing;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Billing\Models\DiscountCampaign;
use App\Modules\Billing\Models\ExamPeriod;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Billing\Models\FeeScheduleItem;
use App\Modules\Billing\Models\FeeType;
use App\Modules\Billing\Models\PaymentPlanOption;
use Database\Seeders\Academics\GradeLevelSeeder;
use Illuminate\Database\Seeder;

class BillingDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(GradeLevelSeeder::class);

        $year = AcademicYear::query()->updateOrCreate(['code' => 'DEV-2026'], ['name' => 'DEVELOPMENT ONLY — SY 2026–2027', 'starts_on' => '2026-06-01', 'ends_on' => '2027-03-31', 'status' => 'closed']);
        $types = collect([
            ['code' => 'TUI', 'name' => 'Tuition Fee', 'category' => 'Tuition', 'is_mandatory' => true, 'is_conditional' => false, 'is_permit_critical' => true, 'max_quantity' => 1],
            ['code' => 'MSC', 'name' => 'Miscellaneous Fee', 'category' => 'School Fee', 'is_mandatory' => true, 'is_conditional' => false, 'is_permit_critical' => true, 'max_quantity' => 1],
            ['code' => 'BKS', 'name' => 'Books', 'category' => 'Learning Materials', 'is_mandatory' => false, 'is_conditional' => true, 'is_permit_critical' => false, 'max_quantity' => 1],
            ['code' => 'PEU', 'name' => 'PE Uniform', 'category' => 'Uniform', 'is_mandatory' => false, 'is_conditional' => false, 'is_permit_critical' => false, 'max_quantity' => 1],
            ['code' => 'LGT', 'name' => 'Logo/Tela', 'category' => 'Uniform', 'is_mandatory' => false, 'is_conditional' => false, 'is_permit_critical' => false, 'max_quantity' => 1],
            ['code' => 'BIB', 'name' => 'Bible', 'category' => 'Learning Materials', 'is_mandatory' => false, 'is_conditional' => true, 'is_permit_critical' => false, 'max_quantity' => 1],
            ['code' => 'GRF', 'name' => 'Graduation/Recognition Fee', 'category' => 'Activity', 'is_mandatory' => false, 'is_conditional' => true, 'is_permit_critical' => false, 'max_quantity' => 1],
        ])->mapWithKeys(fn (array $attributes) => [$attributes['code'] => FeeType::query()->updateOrCreate(['code' => $attributes['code']], $attributes)]);

        foreach (range(1, 12) as $number) {
            $grade = GradeLevel::query()->where('code', 'G'.$number)->firstOrFail();
            [$tuition, $misc, $books, $graduation] = match (true) {
                $number <= 6 => ['24000.00', '4500.00', '4500.00', '2000.00'],
                $number <= 10 => ['27000.00', '5500.00', '5500.00', '2500.00'],
                default => ['30000.00', '6500.00', '6000.00', '3000.00'],
            };
            $schedule = FeeSchedule::query()->updateOrCreate(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'version' => 1], ['name' => 'DEVELOPMENT ONLY — Grade '.$number, 'status' => 'published', 'effective_on' => $year->starts_on]);
            $amounts = ['TUI' => $tuition, 'MSC' => $misc, 'BKS' => $books, 'PEU' => '900.00', 'LGT' => '800.00', 'BIB' => '650.00'];
            if (in_array($number, [6, 10, 12], true)) {
                $amounts['GRF'] = $graduation;
            }
            foreach ($amounts as $code => $amount) {
                FeeScheduleItem::query()->updateOrCreate(['fee_schedule_id' => $schedule->id, 'fee_type_id' => $types[$code]->id], ['description' => $types[$code]->name, 'amount' => $amount, 'is_required' => in_array($code, ['TUI', 'MSC'], true), 'is_default_selected' => in_array($code, ['TUI', 'MSC'], true)]);
            }
            foreach (['full' => 'Full Payment', 'semiannual' => 'Semiannual', 'quarterly' => 'Quarterly', 'monthly' => 'Monthly'] as $code => $name) {
                PaymentPlanOption::query()->updateOrCreate(['academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'code' => $code], ['name' => $name, 'is_active' => true]);
            }
        }

        foreach ([['Q1', 'First Quarterly Examination', '2026-08-20', '2026-08-21'], ['Q2', 'Second Quarterly Examination', '2026-10-22', '2026-10-23'], ['Q3', 'Third Quarterly Examination', '2027-01-21', '2027-01-22'], ['Q4', 'Fourth Quarterly Examination', '2027-03-18', '2027-03-19']] as [$code, $name, $start, $end]) {
            ExamPeriod::query()->updateOrCreate(['academic_year_id' => $year->id, 'code' => $code], ['name' => 'DEVELOPMENT ONLY — '.$name, 'starts_on' => $start, 'ends_on' => $end, 'eligibility_cutoff_on' => date('Y-m-d', strtotime($start.' -10 days')), 'issue_opens_on' => date('Y-m-d', strtotime($start.' -30 days')), 'issue_closes_on' => $end, 'status' => 'planned']);
        }
        DiscountCampaign::query()->updateOrCreate(['academic_year_id' => $year->id, 'code' => 'DEV-FIRST-100'], ['name' => 'DEVELOPMENT ONLY — First 100 Early Enrollees', 'value_type' => 'fixed', 'value_amount' => '1000.00', 'slot_limit' => 100, 'starts_on' => '2026-01-01', 'ends_on' => '2026-05-31', 'is_active' => false, 'is_development_fixture' => true]);
    }
}
