<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Billing\Actions\ManageFeeScheduleAction;
use App\Modules\Billing\Actions\ManagePaymentPlanOptionAction;
use App\Modules\Billing\Actions\SaveBillingConfigurationAction;
use App\Modules\Billing\Http\Requests\SetPaymentPlanOptionStatusRequest;
use App\Modules\Billing\Http\Requests\StorePaymentPlanOptionRequest;
use App\Modules\Billing\Http\Requests\UpdatePaymentPlanOptionRequest;
use App\Modules\Billing\Models\DiscountCampaign;
use App\Modules\Billing\Models\ExamPeriod;
use App\Modules\Billing\Models\FeeSchedule;
use App\Modules\Billing\Models\FeeScheduleItem;
use App\Modules\Billing\Models\FeeType;
use App\Modules\Billing\Models\FinancialPrintSetting;
use App\Modules\Billing\Models\PaymentPlanOption;
use App\Modules\Billing\Models\SchoolNonBusinessDay;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BillingConfigurationController
{
    public function index(): View
    {
        return view('billing.admin.configuration', $this->configurationData());
    }

    public function feeType(Request $request, SaveBillingConfigurationAction $save): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:fee_types,code'], 'name' => ['required', 'string', 'max:255'], 'category' => ['required', 'string', 'max:60'], 'max_quantity' => ['nullable', 'integer', 'min:1', 'max:100'], 'is_mandatory' => ['boolean'], 'is_conditional' => ['boolean'], 'is_permit_critical' => ['boolean']]);
        $save->execute(new FeeType, [...$data, 'is_mandatory' => $request->boolean('is_mandatory'), 'is_conditional' => $request->boolean('is_conditional'), 'is_permit_critical' => $request->boolean('is_permit_critical')], $request->user(), 'billing.fee_type.created');

        return $this->sectionResponse($request, 'fee-catalog', 'Fee type created.');
    }

    public function updateFeeType(FeeType $feeType, Request $request, SaveBillingConfigurationAction $save): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'category' => ['required', 'string', 'max:60'], 'max_quantity' => ['nullable', 'integer', 'min:1', 'max:100'], 'is_mandatory' => ['boolean'], 'is_conditional' => ['boolean'], 'is_permit_critical' => ['boolean'], 'is_active' => ['boolean']]);
        $save->execute($feeType, [...$data, 'is_mandatory' => $request->boolean('is_mandatory'), 'is_conditional' => $request->boolean('is_conditional'), 'is_permit_critical' => $request->boolean('is_permit_critical'), 'is_active' => $request->boolean('is_active')], $request->user(), 'billing.fee_type.updated');

        return $this->sectionResponse($request, 'fee-catalog', 'Fee type configuration updated without changing historical snapshots.');
    }

    public function schedule(Request $request, ManageFeeScheduleAction $manage): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['academic_year_id' => ['required', 'exists:academic_years,id'], 'grade_level_id' => ['nullable', 'exists:grade_levels,id'], 'name' => ['required', 'string', 'max:255'], 'effective_on' => ['nullable', 'date']]);
        $schedule = $manage->create($data, $request->user());

        return $this->sectionResponse($request, 'fee-schedules', "Draft fee schedule created as version {$schedule->version}.");
    }

    public function updateSchedule(FeeSchedule $schedule, Request $request, ManageFeeScheduleAction $manage): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['academic_year_id' => ['required', 'exists:academic_years,id'], 'grade_level_id' => ['nullable', 'exists:grade_levels,id'], 'name' => ['required', 'string', 'max:255'], 'effective_on' => ['nullable', 'date']]);
        $manage->updateDraft($schedule, [...$data, 'grade_level_id' => $data['grade_level_id'] ?? null], $request->user());

        return $this->sectionResponse($request, 'fee-schedules', 'Draft fee schedule updated.');
    }

    public function destroySchedule(FeeSchedule $schedule, Request $request, ManageFeeScheduleAction $manage): RedirectResponse|JsonResponse
    {
        $manage->delete($schedule, $request->user());

        return $this->sectionResponse($request, 'fee-schedules', 'Fee schedule deleted.');
    }

    public function archiveSchedule(FeeSchedule $schedule, Request $request, ManageFeeScheduleAction $manage): RedirectResponse|JsonResponse
    {
        $manage->setArchived($schedule, true, $request->user());

        return $this->sectionResponse($request, 'fee-schedules', 'Fee schedule archived. It is no longer offered for new assessments.');
    }

    public function restoreSchedule(FeeSchedule $schedule, Request $request, ManageFeeScheduleAction $manage): RedirectResponse|JsonResponse
    {
        $manage->setArchived($schedule, false, $request->user());

        return $this->sectionResponse($request, 'fee-schedules', 'Fee schedule restored and available for new assessments.');
    }

    public function scheduleItem(FeeSchedule $schedule, Request $request, SaveBillingConfigurationAction $save): RedirectResponse|JsonResponse
    {
        abort_unless($schedule->status === 'draft', 422);
        $data = $request->validate(['fee_type_id' => ['required', 'exists:fee_types,id', Rule::unique('fee_schedule_items')->where('fee_schedule_id', $schedule->id)], 'description' => ['required', 'string', 'max:255'], 'amount' => ['required', 'decimal:0,2', 'gte:0'], 'is_required' => ['boolean'], 'is_default_selected' => ['boolean']]);
        $save->execute(new FeeScheduleItem, [...$data, 'fee_schedule_id' => $schedule->id, 'is_required' => $request->boolean('is_required'), 'is_default_selected' => $request->boolean('is_default_selected')], $request->user(), 'billing.fee_schedule_item.created');

        return $this->sectionResponse($request, 'fee-schedules', 'Fee schedule item added.');
    }

    public function updateScheduleItem(FeeSchedule $schedule, FeeScheduleItem $item, Request $request, SaveBillingConfigurationAction $save): RedirectResponse|JsonResponse
    {
        abort_unless($schedule->status === 'draft', 422);
        abort_unless($item->fee_schedule_id === $schedule->id, 404);
        $data = $request->validate(['description' => ['required', 'string', 'max:255'], 'amount' => ['required', 'decimal:0,2', 'gte:0'], 'is_required' => ['boolean'], 'is_default_selected' => ['boolean']]);
        $save->execute($item, [...$data, 'is_required' => $request->boolean('is_required'), 'is_default_selected' => $request->boolean('is_default_selected')], $request->user(), 'billing.fee_schedule_item.updated');

        return $this->sectionResponse($request, 'fee-schedules', 'Fee schedule item updated.');
    }

    public function deleteScheduleItem(FeeSchedule $schedule, FeeScheduleItem $item, Request $request, AuditRecorder $audit): RedirectResponse|JsonResponse
    {
        abort_unless($schedule->status === 'draft', 422);
        abort_unless($item->fee_schedule_id === $schedule->id, 404);
        DB::transaction(function () use ($item, $request, $audit): void {
            $audit->record($request->user(), 'billing.fee_schedule_item.deleted', $item);
            $item->delete();
        });

        return $this->sectionResponse($request, 'fee-schedules', 'Fee schedule item removed.');
    }

    public function publish(FeeSchedule $schedule, Request $request, SaveBillingConfigurationAction $save): RedirectResponse|JsonResponse
    {
        abort_unless($schedule->status === 'draft' && $schedule->items()->exists(), 422);
        $save->execute($schedule, ['status' => 'published', 'published_by' => $request->user()->id, 'published_at' => now()], $request->user(), 'billing.fee_schedule.published');

        return $this->sectionResponse($request, 'fee-schedules', 'Fee schedule published and locked for new assessments.');
    }

    public function campaign(Request $request, SaveBillingConfigurationAction $save): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['academic_year_id' => ['required', 'exists:academic_years,id'], 'code' => ['required', 'string', 'max:40'], 'name' => ['required', 'string', 'max:255'], 'value_type' => ['required', Rule::in(['fixed'])], 'value_amount' => ['required', 'decimal:0,2', 'gt:0'], 'slot_limit' => ['nullable', 'integer', 'min:1'], 'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'], 'is_active' => ['boolean']]);
        $save->execute(new DiscountCampaign, [...$data, 'is_active' => $request->boolean('is_active'), 'is_development_fixture' => false], $request->user(), 'billing.discount_campaign.created');

        return $this->sectionResponse($request, 'discounts', 'Discount campaign created.');
    }

    public function period(Request $request, SaveBillingConfigurationAction $save): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['academic_year_id' => ['required', 'exists:academic_years,id'], 'code' => ['required', 'string', 'max:30'], 'name' => ['required', 'string', 'max:255'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after_or_equal:starts_on'], 'eligibility_cutoff_on' => ['required', 'date', 'before_or_equal:starts_on'], 'issue_opens_on' => ['nullable', 'date'], 'issue_closes_on' => ['nullable', 'date', 'after_or_equal:issue_opens_on'], 'status' => ['required', Rule::in(['planned', 'open', 'closed'])]]);
        $save->execute(new ExamPeriod, $data, $request->user(), 'billing.exam_period.created');

        return $this->sectionResponse($request, 'exam-periods', 'Exam period created.');
    }

    public function planOption(StorePaymentPlanOptionRequest $request, ManagePaymentPlanOptionAction $manage): RedirectResponse|JsonResponse
    {
        $manage->create([...$request->validated(), 'is_active' => $request->boolean('is_active')], $request->user());

        return $this->sectionResponse($request, 'payment-plans', 'Payment plan option created.');
    }

    public function setPlanOptionStatus(PaymentPlanOption $planOption, SetPaymentPlanOptionStatusRequest $request, ManagePaymentPlanOptionAction $manage): RedirectResponse|JsonResponse
    {
        $isActive = $request->boolean('is_active');
        $manage->setActive($planOption, $isActive, $request->validated('reason'), $request->user());

        return $this->sectionResponse($request, 'payment-plans', $isActive
            ? 'Payment plan activated for future plan selections.'
            : 'Payment plan deactivated. Existing assessments and payment history were preserved.');
    }

    public function updatePlanOption(PaymentPlanOption $planOption, UpdatePaymentPlanOptionRequest $request, ManagePaymentPlanOptionAction $manage): RedirectResponse|JsonResponse
    {
        $manage->rename($planOption, $request->validated('name'), $request->user());

        return $this->sectionResponse($request, 'payment-plans', 'Payment plan name updated.');
    }

    public function deletePlanOption(PaymentPlanOption $planOption, ManagePaymentPlanOptionAction $manage, Request $request): RedirectResponse|JsonResponse
    {
        $manage->delete($planOption, $request->user());

        return $this->sectionResponse($request, 'payment-plans', 'Payment plan deleted.');
    }

    public function nonBusinessDay(Request $request, SaveBillingConfigurationAction $save): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['academic_year_id' => ['required', 'exists:academic_years,id'], 'calendar_date' => ['required', 'date'], 'label' => ['required', 'string', 'max:255']]);
        $save->execute(new SchoolNonBusinessDay, $data, $request->user(), 'billing.non_business_day.created');

        return $this->sectionResponse($request, 'calendar', 'Non-business day added.');
    }

    public function printSetting(Request $request, SaveBillingConfigurationAction $save): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['academic_year_id' => ['required', 'exists:academic_years,id'], 'setting_key' => ['required', Rule::in(['receipt_signatory_role', 'permit_signatory_role', 'receipt_footer', 'permit_footer'])], 'setting_value' => ['nullable', 'string', 'max:1000']]);
        $setting = FinancialPrintSetting::query()->firstOrNew(['academic_year_id' => $data['academic_year_id'], 'setting_key' => $data['setting_key']]);
        $save->execute($setting, [...$data, 'updated_by' => $request->user()->id], $request->user(), 'billing.print_setting.saved');

        return $this->sectionResponse($request, 'print-settings', 'Print setting saved.');
    }

    /**
     * On an AJAX request return just the re-rendered section as JSON so the
     * page can swap it in place without a full reload; otherwise fall back to
     * the classic redirect-with-flash so non-JS clients (and feature tests)
     * keep working.
     */
    private function sectionResponse(Request $request, string $section, string $message): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'section' => $section,
                'html' => view("billing.admin.partials.{$section}", $this->configurationData())->render(),
                'message' => $message,
            ]);
        }

        return back()->with('status', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function configurationData(): array
    {
        $planOptionRows = PaymentPlanOption::query()
            ->with(['academicYear', 'gradeLevel'])
            ->orderByDesc('academic_year_id')
            ->orderBy('grade_level_id')
            ->orderBy('code')
            ->get();

        $planOptions = app(ManagePaymentPlanOptionAction::class);

        // Ordered so every version of the same year and grade reads together,
        // newest series first — the two "Grade 3" cards are otherwise identical
        // on screen.
        $schedules = FeeSchedule::query()
            ->with(['items.feeType', 'academicYear', 'gradeLevel'])
            ->withCount('assessments')
            ->orderByDesc('academic_year_id')
            ->orderBy('grade_level_id')
            ->orderByDesc('version')
            ->get();

        return [
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(),
            'gradeLevels' => GradeLevel::query()->where('is_active', true)->orderBy('sequence')->get(),
            'feeTypes' => FeeType::query()->orderBy('code')->get(),
            'schedules' => $schedules,
            'scheduleUsage' => $schedules->mapWithKeys(
                fn (FeeSchedule $schedule) => [$schedule->id => (int) $schedule->assessments_count],
            ),
            'campaigns' => DiscountCampaign::query()->latest()->get(),
            'periods' => ExamPeriod::query()->orderBy('starts_on')->get(),
            'planOptions' => $planOptionRows,
            'planOptionUsage' => $planOptionRows->mapWithKeys(
                fn (PaymentPlanOption $option) => [$option->id => $planOptions->usageCount($option)]
            ),
            'nonBusinessDays' => SchoolNonBusinessDay::query()->orderBy('calendar_date')->get(),
        ];
    }
}
