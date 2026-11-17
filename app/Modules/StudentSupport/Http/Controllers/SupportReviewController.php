<?php

namespace App\Modules\StudentSupport\Http\Controllers;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Shared\Audit\AuditRecorder;
use App\Modules\StudentSupport\Actions\CreateSupportCaseAction;
use App\Modules\StudentSupport\Actions\ManageSupportCaseAction;
use App\Modules\StudentSupport\Models\SupportCase;
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

        $cases = SupportCase::query()->with('enrollment.student');
        if (! request()->user()->hasRole('admin')) {
            $cases->where('assigned_reviewer_id', request()->user()->id);
        }

        return view('support.queue', [
            'cases' => $cases->latest()->get(),
            'reviewers' => request()->user()->hasRole('admin') ? User::query()->whereHas('roles', fn ($roles) => $roles->whereIn('code', ['admin', 'registrar']))->orderBy('name')->get() : collect(),
        ]);
    }

    public function show(SupportCase $supportCase, Request $request, AuditRecorder $audit): View
    {
        Gate::authorize('view', $supportCase);
        DB::table('support_access_logs')->insert(['support_case_id' => $supportCase->id, 'actor_user_id' => $request->user()->id, 'action' => 'view_raw', 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $audit->record($request->user(), 'support.raw.viewed', $supportCase);

        return view('support.show', ['case' => $supportCase->load(['enrollment.student', 'enrollment.gradeLevel', 'enrollment.academicYear', 'assessments', 'observations', 'summaries', 'events'])]);
    }

    public function create(string $enrollment, Request $request, CreateSupportCaseAction $create): RedirectResponse
    {
        Gate::authorize('create', SupportCase::class);
        $record = Enrollment::query()->where('public_id', $enrollment)->firstOrFail();
        $create->execute($record, $request->user());

        return back()->with('status', 'Restricted support case opened.');
    }

    public function assign(SupportCase $supportCase, Request $request, ManageSupportCaseAction $manage): RedirectResponse
    {
        Gate::authorize('assign', $supportCase);
        $data = $request->validate(['reviewer_user_id' => ['required', 'exists:users,id']]);
        $reviewer = User::query()->findOrFail($data['reviewer_user_id']);
        abort_unless($reviewer->isActive() && $reviewer->hasAnyRole(['admin', 'registrar']), 422, 'Reviewer must be an active authorized staff account.');
        $manage->assign($supportCase, $reviewer, $request->user());

        return back()->with('status', 'Reviewer assigned.');
    }

    public function assessment(SupportCase $supportCase, Request $request, ManageSupportCaseAction $manage): RedirectResponse
    {
        Gate::authorize('record', $supportCase);
        $data = $request->validate(['outcome' => ['required', 'in:pending,approved,not_approved,more_information_required'], 'confidential_rationale' => ['nullable', 'string', 'max:5000'], 'next_action' => ['nullable', 'string', 'max:100']]);
        $manage->recordAssessment($supportCase, $data, $request->user());

        return back()->with('status', 'Generic review outcome recorded.');
    }

    public function observation(SupportCase $supportCase, Request $request, ManageSupportCaseAction $manage): RedirectResponse
    {
        Gate::authorize('record', $supportCase);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'], 'category' => ['nullable', 'string', 'max:60']]);
        $manage->addObservation($supportCase, $data, $request->user());

        return back()->with('status', 'Restricted internal note saved.');
    }

    public function summary(SupportCase $supportCase, Request $request, ManageSupportCaseAction $manage): RedirectResponse
    {
        Gate::authorize('summary', $supportCase);
        $data = $request->validate(['summary' => ['required', 'string', 'max:3000'], 'effective_from' => ['nullable', 'date'], 'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from']]);
        $manage->approveSummary($supportCase, $data, $request->user());

        return back()->with('status', 'Teacher-safe summary approved.');
    }
}
