<?php

use App\Modules\Billing\Http\Controllers\BillingConfigurationController;
use App\Modules\Billing\Http\Controllers\BillingController;
use App\Modules\Billing\Http\Controllers\ExamPermitController;
use App\Modules\Billing\Http\Controllers\FinancialPortalController;
use App\Modules\Billing\Http\Controllers\FinancialPrintController;
use App\Modules\Billing\Http\Controllers\PaymentEvidenceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'password.changed'])->group(function (): void {
    Route::get('/finance', [FinancialPortalController::class, 'index'])->name('billing.portal.index');
    Route::get('/finance/assessments/{assessment}', [FinancialPortalController::class, 'show'])->name('billing.portal.show');
    Route::get('/finance/assessments/{assessment}/statement', [FinancialPrintController::class, 'statement'])->name('billing.print.statement');
    Route::get('/finance/payments/{payment}/receipt', [FinancialPrintController::class, 'receipt'])->name('billing.print.receipt');
    Route::get('/finance/permits/{permit}/print', [FinancialPrintController::class, 'permit'])->name('billing.print.permit');
    Route::get('/teaching/enrollments/{enrollment}/exam-status', [ExamPermitController::class, 'teacherStatus'])->name('billing.teacher.status');
    Route::middleware('role:admin,accounting,registrar')->prefix('billing')->group(function (): void {
        Route::get('/', [BillingController::class, 'index'])->name('billing.index');
    });
    Route::middleware('role:admin,accounting')->prefix('billing')->group(function (): void {
        Route::get('/assessments/{assessment}/ledger', [FinancialPrintController::class, 'ledger'])->name('billing.print.ledger');
    });
    Route::middleware('role:accounting')->prefix('billing')->group(function (): void {
        Route::post('/enrollments/{enrollment}/assessments', [BillingController::class, 'createAssessment'])->name('billing.assessments.create');
        Route::post('/assessments/{assessment}/finalize', [BillingController::class, 'finalize'])->name('billing.assessments.finalize');
        Route::put('/assessments/{assessment}', [BillingController::class, 'updateAssessment'])->name('billing.assessments.update');
        Route::post('/assessments/{assessment}/cancel', [BillingController::class, 'cancelAssessment'])->name('billing.assessments.cancel');
        Route::post('/assessments/{assessment}/payments', [BillingController::class, 'payment'])->name('billing.payments.create');
        Route::post('/payments/{payment}/post', [BillingController::class, 'post'])->name('billing.payments.post');
        Route::put('/payments/{payment}', [BillingController::class, 'updatePayment'])->name('billing.payments.update');
        Route::post('/payments/{payment}/cancel', [BillingController::class, 'cancelPayment'])->name('billing.payments.cancel');
        Route::post('/assessments/{assessment}/plans', [BillingController::class, 'selectPlan'])->name('billing.plans.select');
        Route::post('/assessments/{assessment}/payment-to-follow', [BillingController::class, 'paymentToFollow'])->name('billing.payment-to-follow');
        Route::post('/payments/{payment}/reversals', [BillingController::class, 'requestReversal'])->name('billing.reversals.request');
        Route::post('/payments/{payment}/credit-allocations', [BillingController::class, 'allocateCredit'])->name('billing.credit.allocate');
        Route::post('/payments/{payment}/evidence', [PaymentEvidenceController::class, 'store'])->name('billing.evidence.store');
        Route::post('/assessments/{assessment}/adjustments', [BillingController::class, 'requestAdjustment'])->name('billing.adjustments.request');
        Route::post('/assessments/{assessment}/refunds', [BillingController::class, 'requestRefund'])->name('billing.refunds.request');
        Route::post('/discount-awards/{award}/cancellation', [BillingController::class, 'requestDiscountCancellation'])->name('billing.discounts.cancellation.request');
        Route::post('/assessments/{assessment}/periods/{period}/permit', [ExamPermitController::class, 'issue'])->name('billing.permits.issue');
    });
    Route::middleware('role:accounting,registrar')->post('/billing/enrollments/{enrollment}/permit-overrides', [ExamPermitController::class, 'requestOverride'])->name('billing.permit-overrides.request');
    Route::middleware('role:admin')->prefix('billing')->group(function (): void {
        Route::get('/configuration', [BillingConfigurationController::class, 'index'])->name('billing.configuration.index');
        Route::post('/configuration/fee-types', [BillingConfigurationController::class, 'feeType'])->name('billing.configuration.fee-types');
        Route::patch('/configuration/fee-types/{feeType}', [BillingConfigurationController::class, 'updateFeeType'])->name('billing.configuration.fee-types.update');
        Route::post('/configuration/schedules', [BillingConfigurationController::class, 'schedule'])->name('billing.configuration.schedules');
        Route::put('/configuration/schedules/{schedule}', [BillingConfigurationController::class, 'updateSchedule'])->name('billing.configuration.schedules.update');
        Route::delete('/configuration/schedules/{schedule}', [BillingConfigurationController::class, 'destroySchedule'])->name('billing.configuration.schedules.destroy');
        Route::post('/configuration/schedules/{schedule}/archive', [BillingConfigurationController::class, 'archiveSchedule'])->name('billing.configuration.schedules.archive');
        Route::post('/configuration/schedules/{schedule}/restore', [BillingConfigurationController::class, 'restoreSchedule'])->name('billing.configuration.schedules.restore');
        Route::post('/configuration/schedules/{schedule}/items', [BillingConfigurationController::class, 'scheduleItem'])->name('billing.configuration.schedule-items');
        Route::put('/configuration/schedules/{schedule}/items/{item}', [BillingConfigurationController::class, 'updateScheduleItem'])->name('billing.configuration.schedule-items.update');
        Route::delete('/configuration/schedules/{schedule}/items/{item}', [BillingConfigurationController::class, 'deleteScheduleItem'])->name('billing.configuration.schedule-items.destroy');
        Route::post('/configuration/schedules/{schedule}/publish', [BillingConfigurationController::class, 'publish'])->name('billing.configuration.schedule-publish');
        Route::post('/configuration/campaigns', [BillingConfigurationController::class, 'campaign'])->name('billing.configuration.campaigns');
        Route::post('/configuration/exam-periods', [BillingConfigurationController::class, 'period'])->name('billing.configuration.exam-periods');
        Route::post('/configuration/plan-options', [BillingConfigurationController::class, 'planOption'])->name('billing.configuration.plan-options');
        Route::patch('/configuration/plan-options/{planOption}', [BillingConfigurationController::class, 'setPlanOptionStatus'])->name('billing.configuration.plan-options.status');
        Route::put('/configuration/plan-options/{planOption}', [BillingConfigurationController::class, 'updatePlanOption'])->name('billing.configuration.plan-options.update');
        Route::delete('/configuration/plan-options/{planOption}', [BillingConfigurationController::class, 'deletePlanOption'])->name('billing.configuration.plan-options.destroy');
        Route::post('/configuration/non-business-days', [BillingConfigurationController::class, 'nonBusinessDay'])->name('billing.configuration.non-business-days');
        Route::post('/configuration/print-settings', [BillingConfigurationController::class, 'printSetting'])->name('billing.configuration.print-settings');
        Route::post('/reversals/{reversal}/decision', [BillingController::class, 'decideReversal'])->name('billing.reversals.decide');
        Route::post('/adjustments/{adjustment}/decision', [BillingController::class, 'decideAdjustment'])->name('billing.adjustments.decide');
        Route::post('/refunds/{refund}/decision', [BillingController::class, 'decideRefund'])->name('billing.refunds.decide');
        Route::post('/discount-awards/{award}/cancellation-decision', [BillingController::class, 'decideDiscountCancellation'])->name('billing.discounts.cancellation.decide');
        Route::post('/discount-awards/{award}/reopen-slot', [BillingController::class, 'reopenDiscountSlot'])->name('billing.discounts.reopen-slot');
        Route::post('/permit-overrides/{override}/decision', [ExamPermitController::class, 'decideOverride'])->name('billing.permit-overrides.decide');
        Route::post('/permit-overrides/{override}/revoke', [ExamPermitController::class, 'revokeOverride'])->name('billing.permit-overrides.revoke');
        Route::post('/permits/{permit}/revoke', [ExamPermitController::class, 'revoke'])->name('billing.permits.revoke');
    });
    Route::middleware('role:admin,accounting')->get('/billing/evidence/{evidence}/download', [PaymentEvidenceController::class, 'download'])->name('billing.evidence.download');
});
