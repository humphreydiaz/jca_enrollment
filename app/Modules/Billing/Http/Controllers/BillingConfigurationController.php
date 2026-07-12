<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Billing\Actions\SaveBillingConfigurationAction;
use App\Modules\Billing\Models\DiscountCampaign;
use App\Modules\Billing\Models\ExamPeriod;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Billing\Models\FeeScheduleItem;
use App\Modules\Billing\Models\FeeType;
use App\Modules\Billing\Models\FinancialPrintSetting;
use App\Modules\Billing\Models\PaymentPlanOption;
use App\Modules\Billing\Models\SchoolNonBusinessDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BillingConfigurationController
{
    public function index(): View
    {
        return view('billing.admin.configuration', [
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'gradeLevels' => GradeLevel::query()->where('is_active', true)->orderBy('sequence')->get(),
            'feeTypes' => FeeType::query()->orderBy('code')->get(),
            'schedules' => FeeSchedule::query()->with('items.feeType')->latest()->get(),
            'campaigns' => DiscountCampaign::query()->latest()->get(),
            'periods' => ExamPeriod::query()->orderBy('starts_on')->get(),
            'planOptions' => PaymentPlanOption::query()->orderBy('code')->get(),
        ]);
    }

    public function feeType(Request $request, SaveBillingConfigurationAction $save): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:fee_types,code'], 'name' => ['required', 'string', 'max:255'], 'category' => ['required', 'string', 'max:60'], 'max_quantity' => ['nullable', 'integer', 'min:1', 'max:100'], 'is_mandatory' => ['boolean'], 'is_conditional' => ['boolean'], 'is_permit_critical' => ['boolean']]);
        $save->execute(new FeeType, [...$data, 'is_mandatory' => $request->boolean('is_mandatory'), 'is_conditional' => $request->boolean('is_conditional'), 'is_permit_critical' => $request->boolean('is_permit_critical')], $request->user(), 'billing.fee_type.created');

        return back()->with('status', 'Fee type created.');
    }

    public function updateFeeType(FeeType $feeType, Request $request, SaveBillingConfigurationAction $save): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'category' => ['required', 'string', 'max:60'], 'max_quantity' => ['nullable', 'integer', 'min:1', 'max:100'], 'is_mandatory' => ['boolean'], 'is_conditional' => ['boolean'], 'is_permit_critical' => ['boolean'], 'is_active' => ['boolean']]);
        $save->execute($feeType, [...$data, 'is_mandatory' => $request->boolean('is_mandatory'), 'is_conditional' => $request->boolean('is_conditional'), 'is_permit_critical' => $request->boolean('is_permit_critical'), 'is_active' => $request->boolean('is_active')], $request->user(), 'billing.fee_type.updated');

        return back()->with('status', 'Fee type configuration updated without changing historical snapshots.');
    }

    public function schedule(Request $request, SaveBillingConfigurationAction $save): RedirectResponse
    {
        $data = $request->validate(['academic_year_id' => ['required', 'exists:academic_years,id'], 'grade_level_id' => ['nullable', 'exists:grade_levels,id'], 'name' => ['required', 'string', 'max:255'], 'version' => ['required', 'integer', 'min:1'], 'effective_on' => ['nullable', 'date']]);
        $save->execute(new FeeSchedule, [...$data, 'status' => 'draft'], $request->user(), 'billing.fee_schedule.created');

        return back()->with('status', 'Draft fee schedule created.');
    }

    public function scheduleItem(FeeSchedule $schedule, Request $request, SaveBillingConfigurationAction $save): RedirectResponse
    {
        abort_unless($schedule->status === 'draft', 422);
        $data = $request->validate(['fee_type_id' => ['required', 'exists:fee_types,id', Rule::unique('fee_schedule_items')->where('fee_schedule_id', $schedule->id)], 'description' => ['required', 'string', 'max:255'], 'amount' => ['required', 'decimal:0,2', 'gte:0'], 'is_required' => ['boolean'], 'is_default_selected' => ['boolean']]);
        $save->execute(new FeeScheduleItem, [...$data, 'fee_schedule_id' => $schedule->id, 'is_required' => $request->boolean('is_required'), 'is_default_selected' => $request->boolean('is_default_selected')], $request->user(), 'billing.fee_schedule_item.created');

        return back()->with('status', 'Fee schedule item added.');
    }

    public function publish(FeeSchedule $schedule, Request $request, SaveBillingConfigurationAction $save): RedirectResponse
    {
        abort_unless($schedule->status === 'draft' && $schedule->items()->exists(), 422);
        $save->execute($schedule, ['status' => 'published', 'published_by' => $request->user()->id, 'published_at' => now()], $request->user(), 'billing.fee_schedule.published');

        return back()->with('status', 'Fee schedule published and locked for new assessments.');
    }

    public function campaign(Request $request, SaveBillingConfigurationAction $save): RedirectResponse
    {
        $data = $request->validate(['academic_year_id' => ['required', 'exists:academic_years,id'], 'code' => ['required', 'string', 'max:40'], 'name' => ['required', 'string', 'max:255'], 'value_type' => ['required', Rule::in(['fixed'])], 'value_amount' => ['required', 'decimal:0,2', 'gt:0'], 'slot_limit' => ['nullable', 'integer', 'min:1'], 'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'], 'is_active' => ['boolean']]);
        $save->execute(new DiscountCampaign, [...$data, 'is_active' => $request->boolean('is_active'), 'is_development_fixture' => false], $request->user(), 'billing.discount_campaign.created');

        return back()->with('status', 'Discount campaign created.');
    }

    public function period(Request $request, SaveBillingConfigurationAction $save): RedirectResponse
    {
        $data = $request->validate(['academic_year_id' => ['required', 'exists:academic_years,id'], 'code' => ['required', 'string', 'max:30'], 'name' => ['required', 'string', 'max:255'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'], 'eligibility_cutoff_on' => ['required', 'date', 'before_or_equal:starts_on'], 'issue_opens_on' => ['nullable', 'date'], 'issue_closes_on' => ['nullable', 'date', 'after_or_equal:issue_opens_on'], 'status' => ['required', Rule::in(['planned', 'open', 'closed'])]]);
        $save->execute(new ExamPeriod, $data, $request->user(), 'billing.exam_period.created');

        return back()->with('status', 'Exam period created.');
    }

    public function planOption(Request $request, SaveBillingConfigurationAction $save): RedirectResponse
    {
        $data = $request->validate(['academic_year_id' => ['required', 'exists:academic_years,id'], 'grade_level_id' => ['nullable', 'exists:grade_levels,id'], 'code' => ['required', Rule::in(['full', 'semiannual', 'quarterly', 'monthly'])], 'name' => ['required', 'string', 'max:255'], 'is_active' => ['boolean']]);
        $save->execute(new PaymentPlanOption, [...$data, 'is_active' => $request->boolean('is_active')], $request->user(), 'billing.payment_plan_option.created');

        return back()->with('status', 'Payment plan option created.');
    }

    public function nonBusinessDay(Request $request, SaveBillingConfigurationAction $save): RedirectResponse
    {
        $data = $request->validate(['academic_year_id' => ['required', 'exists:academic_years,id'], 'calendar_date' => ['required', 'date'], 'label' => ['required', 'string', 'max:255']]);
        $save->execute(new SchoolNonBusinessDay, $data, $request->user(), 'billing.non_business_day.created');

        return back()->with('status', 'Non-business day added.');
    }

    public function printSetting(Request $request, SaveBillingConfigurationAction $save): RedirectResponse
    {
        $data = $request->validate(['academic_year_id' => ['required', 'exists:academic_years,id'], 'setting_key' => ['required', Rule::in(['receipt_signatory_role', 'permit_signatory_role', 'receipt_footer', 'permit_footer'])], 'setting_value' => ['nullable', 'string', 'max:1000']]);
        $setting = FinancialPrintSetting::query()->firstOrNew(['academic_year_id' => $data['academic_year_id'], 'setting_key' => $data['setting_key']]);
        $save->execute($setting, [...$data, 'updated_by' => $request->user()->id], $request->user(), 'billing.print_setting.saved');

        return back()->with('status', 'Print setting saved.');
    }
}
