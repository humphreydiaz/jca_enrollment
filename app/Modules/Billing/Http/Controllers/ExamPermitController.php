<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Billing\Actions\ApprovePermitOverrideAction;
use App\Modules\Billing\Actions\IssueExamPermitAction;
use App\Modules\Billing\Actions\RequestPermitOverrideAction;
use App\Modules\Billing\Actions\RevokeExamPermitAction;
use App\Modules\Billing\Actions\RevokePermitOverrideAction;
use App\Modules\Billing\Models\ExamPeriod;
use App\Modules\Billing\Models\ExamPermit;
use App\Modules\Billing\Models\ExamPermitOverride;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Enrollment\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExamPermitController
{
    public function issue(FeeAssessment $assessment, ExamPeriod $period, Request $request, IssueExamPermitAction $issue): RedirectResponse
    {
        Gate::authorize('issue', ExamPermit::class);
        abort_unless($period->academic_year_id === $assessment->enrollment->academic_year_id, 404);
        $issue->execute($assessment, $period, $request->user());

        return back()->with('status', 'Exam permit issued.');
    }

    public function requestOverride(Enrollment $enrollment, Request $request, RequestPermitOverrideAction $create): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['accounting', 'registrar']), 403);
        $data = $request->validate(['exam_period_id' => ['nullable', 'integer', 'exists:exam_periods,id'], 'reason_category' => ['required', Rule::in(['statutory_entitlement', 'promissory_note', 'financial_hardship', 'billing_correction', 'emergency'])], 'confidential_reason' => ['required', 'string', 'max:4000'], 'supporting_document_reference' => ['nullable', 'string', 'max:255'], 'effective_from' => ['required', 'date'], 'expires_on' => ['nullable', 'date', 'after_or_equal:effective_from']]);
        $create->execute($enrollment, $data, $request->user());

        return back()->with('status', 'Permit exception submitted for Admin approval.');
    }

    public function decideOverride(ExamPermitOverride $override, Request $request, ApprovePermitOverrideAction $decide): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean'], 'decision_reason' => ['required', 'string', 'max:2000']]);
        $decide->execute($override, $request->user(), $request->boolean('approve'), $data['decision_reason']);

        return back()->with('status', 'Permit override decision recorded.');
    }

    public function teacherStatus(Enrollment $enrollment, Request $request): View
    {
        abort_unless($request->user()->hasRole('teacher'), 403);
        $staffId = $request->user()->staffProfile?->id;
        $sectionId = $enrollment->currentSectionAssignment?->section_id;
        $assigned = $staffId && $sectionId && TeachingAssignment::query()
            ->where('staff_profile_id', $staffId)->where('section_id', $sectionId)->where('is_active', true)
            ->whereDate('active_from', '<=', today())->where(fn ($query) => $query->whereNull('active_until')->orWhereDate('active_until', '>=', today()))->exists();
        abort_unless($assigned, 404);
        $permit = ExamPermit::query()->where('enrollment_id', $enrollment->id)->where('status', 'issued')->latest('issued_at')->first();

        return view('billing.teacher.status', ['enrollment' => $enrollment->load('student'), 'permit' => $permit]);
    }

    public function revoke(ExamPermit $permit, Request $request, RevokeExamPermitAction $revoke): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $revoke->execute($permit, $data['reason'], $request->user());

        return back()->with('status', 'Permit revoked; its history remains available.');
    }

    public function revokeOverride(ExamPermitOverride $override, Request $request, RevokePermitOverrideAction $revoke): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $revoke->execute($override, $data['reason'], $request->user());

        return back()->with('status', 'Permit override revoked.');
    }
}
