<?php

namespace App\Modules\StudentSupport\Http\Controllers;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Shared\Audit\AuditRecorder;
use App\Modules\StudentSupport\Actions\CreateSupportCaseAction;
use App\Modules\StudentSupport\Models\ApprovedSupportSummary;
use App\Modules\StudentSupport\Models\SupportAssessment;
use App\Modules\StudentSupport\Models\SupportCase;
use App\Modules\StudentSupport\Models\SupportObservation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SupportReviewController
{
    public function index(): View
    {
        Gate::authorize('viewAny', SupportCase::class);

        return view('support.queue', ['cases' => SupportCase::query()->with('enrollment.student')->latest()->get()]);
    }

    public function show(SupportCase $supportCase, Request $request, AuditRecorder $audit): View
    {
        Gate::authorize('view', $supportCase);
        DB::table('support_access_logs')->insert(['support_case_id' => $supportCase->id, 'actor_user_id' => $request->user()->id, 'action' => 'view_raw', 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $audit->record($request->user(), 'support.raw.viewed', $supportCase);

        return view('support.show', ['case' => $supportCase->load(['enrollment.student', 'assessments', 'observations', 'summaries'])]);
    }

    public function create(Enrollment $enrollment, Request $request, CreateSupportCaseAction $create): RedirectResponse
    {
        Gate::authorize('create', SupportCase::class);
        $create->execute($enrollment, $request->user());

        return back()->with('status', 'Restricted support case opened.');
    }

    public function assign(SupportCase $supportCase, Request $request): RedirectResponse
    {
        Gate::authorize('assign', $supportCase);
        $data = $request->validate(['reviewer_user_id' => ['required', 'exists:users,id']]);
        $reviewer = User::query()->findOrFail($data['reviewer_user_id']);
        abort_unless($reviewer->hasAnyRole(['admin', 'registrar']), 422, 'Reviewer must be an authorized staff account.');
        $supportCase->update(['assigned_reviewer_id' => $reviewer->id, 'status' => 'assigned']);

        return back()->with('status', 'Reviewer assigned.');
    }

    public function assessment(SupportCase $supportCase, Request $request): RedirectResponse
    {
        Gate::authorize('record', $supportCase);
        $data = $request->validate(['outcome' => ['required', 'in:pending,approved,not_approved,more_information_required'], 'confidential_rationale' => ['nullable', 'string', 'max:5000'], 'next_action' => ['nullable', 'string', 'max:100']]);
        SupportAssessment::query()->create(array_merge($data, ['support_case_id' => $supportCase->id, 'assessment_type' => 'school_review', 'completed_on' => now(), 'reviewer_id' => $request->user()->id]));
        $supportCase->update(['status' => $data['outcome'] === 'pending' ? 'assigned' : 'reviewed', 'final_outcome' => $data['outcome'] === 'approved' ? 'approved' : null]);

        return back()->with('status', 'Generic review outcome recorded.');
    }

    public function observation(SupportCase $supportCase, Request $request): RedirectResponse
    {
        Gate::authorize('record', $supportCase);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'], 'category' => ['nullable', 'string', 'max:60']]);
        SupportObservation::query()->create(array_merge($data, ['support_case_id' => $supportCase->id, 'author_id' => $request->user()->id, 'observed_at' => now()]));

        return back()->with('status', 'Restricted internal note saved.');
    }

    public function summary(SupportCase $supportCase, Request $request): RedirectResponse
    {
        Gate::authorize('summary', $supportCase);
        $data = $request->validate(['summary' => ['required', 'string', 'max:3000'], 'effective_from' => ['nullable', 'date'], 'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from']]);
        ApprovedSupportSummary::query()->create(array_merge($data, ['support_case_id' => $supportCase->id, 'approved_by' => $request->user()->id, 'approved_at' => now()]));

        return back()->with('status','Teacher-safe summary approved.');
    }
}
