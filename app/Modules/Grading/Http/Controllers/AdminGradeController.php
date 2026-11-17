<?php

namespace App\Modules\Grading\Http\Controllers;

use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Subject;
use App\Modules\Grading\Actions\ApproveGradesAction;
use App\Modules\Grading\Actions\ApproveReviewedClassesAction;
use App\Modules\Grading\Actions\FinalizeGradeAction;
use App\Modules\Grading\Actions\RecordClassReviewAction;
use App\Modules\Grading\Actions\ReviewGradeCorrectionAction;
use App\Modules\Grading\Http\Requests\ApproveClassesRequest;
use App\Modules\Grading\Http\Requests\ApproveGradesRequest;
use App\Modules\Grading\Http\Requests\GradeApprovalFilterRequest;
use App\Modules\Grading\Http\Requests\ReviewClassesRequest;
use App\Modules\Grading\Http\Requests\ReviewGradeCorrectionRequest;
use App\Modules\Grading\Models\GradeCorrectionRequest;
use App\Modules\Grading\Models\StudentSubjectGrade;
use App\Modules\Grading\Queries\GradeApprovalBoardQuery;
use App\Modules\Grading\Support\GradeFingerprint;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Grade approval for the Registrar (and Admin): track submissions, review classes, approve, and decide reopen requests. */
class AdminGradeController
{
    /** Session key holding what the reviewer saw in each opened class: class key => [grade public id => fingerprint]. */
    public const REVIEWED = 'grading_reviewed';

    public function index(GradeApprovalFilterRequest $request, GradeApprovalBoardQuery $board, AuditRecorder $audit): View
    {
        Gate::authorize('viewAny', StudentSubjectGrade::class);
        $input = $request->validated();
        $filters = $board->filters($input);
        $tab = $input['tab'] ?? 'waiting';
        $classes = $board->classes($filters);

        $waiting = $classes->filter(fn (array $class): bool => $class['counts']['submitted'] > 0)
            ->sortBy(fn (array $class): int => $class['oldestSubmittedAt']?->getTimestamp() ?? PHP_INT_MAX)->values();
        $missing = $classes->filter(fn (array $class): bool => $class['remaining'] > 0)
            ->groupBy('teacherNames')
            ->map(fn (Collection $group, string $teacher): array => ['teacher' => $teacher, 'classes' => $group->values(), 'remaining' => $group->sum('remaining')])
            ->sortByDesc('remaining')->values();
        $scope = fn (Builder $query) => $board->scopeGrades($query, $filters);
        $correctionsQuery = GradeCorrectionRequest::query()->where('status', 'pending')->whereHas('grade', $scope);

        $totals = [
            'classes' => $classes->count(),
            'fullySubmitted' => $classes->where('remaining', 0)->count(),
            'notSubmittedClasses' => $classes->where('remaining', '>', 0)->count(),
            'missingGrades' => $classes->sum('remaining'),
            'waitingGrades' => $waiting->sum(fn (array $class): int => $class['counts']['submitted']),
            'waitingClasses' => $waiting->count(),
            'approvedGrades' => $classes->sum(fn (array $class): int => $class['counts']['approved']),
            'reopen' => (clone $correctionsQuery)->count(),
            'grades' => $classes->sum('total'),
        ];

        $page = match ($tab) {
            'waiting' => $this->paginate($waiting, 20, $request),
            'missing' => $this->paginate($missing, 10, $request),
            'reopen' => $correctionsQuery->with(['grade.enrollment.student', 'grade.enrollment.gradeLevel', 'grade.sectionSubject.section'])
                ->oldest()->paginate(20)->withQueryString(),
            'approved' => StudentSubjectGrade::query()->where('status', 'finalized')->where($scope)
                ->with(['enrollment.student', 'enrollment.gradeLevel', 'sectionSubject.section'])
                ->latest('finalized_at')->paginate(25)->withQueryString(),
        };
        $audit->record($request->user(), 'grading.admin.queue_viewed');

        return view('grading.admin-index', [
            ...$filters,
            'tab' => $tab,
            'classes' => $classes,
            'totals' => $totals,
            'page' => $page,
            'reviewedKeys' => array_keys($request->session()->get(self::REVIEWED, [])),
            'preselect' => (array) $request->session()->get('grading_preselect', []),
            'query' => array_filter($input, fn ($value) => $value !== null && $value !== ''),
        ]);
    }

    /** One class for review. Loaded into the page's dialog, or as a full page without JavaScript. */
    public function showClass(Request $request, Section $section, Subject $subject, GradeApprovalBoardQuery $board, AuditRecorder $audit): Response
    {
        Gate::authorize('viewAny', StudentSubjectGrade::class);
        $detail = $board->classDetail($section, $subject);
        abort_if($detail === null, 404);

        $snapshot = $detail['approvable']->mapWithKeys(fn (StudentSubjectGrade $grade): array => [$grade->public_id => GradeFingerprint::of($grade)])->all();
        $snapshot === []
            ? $request->session()->forget(self::REVIEWED.'.'.$detail['key'])
            : $request->session()->put(self::REVIEWED.'.'.$detail['key'], $snapshot);
        $audit->record($request->user(), 'grading.admin.class_reviewed', $section, ['subject' => $subject->public_id, 'waiting' => count($snapshot)]);

        return response()
            ->view($request->ajax() ? 'grading.partials.class-review' : 'grading.admin-class', ['class' => $detail])
            ->header('Cache-Control', 'no-store, private');
    }

    /** "Review all": marks every class shown on the Waiting list as reviewed, exactly as shown. */
    public function reviewClasses(ReviewClassesRequest $request, RecordClassReviewAction $record): RedirectResponse
    {
        $result = $record->execute($request->validated('seen'), $request->user());
        foreach ($result['snapshots'] as $key => $snapshot) {
            $request->session()->put(self::REVIEWED.'.'.$key, $snapshot);
        }

        $classes = count($result['snapshots']);
        $message = 'Marked '.$classes.' '.str('class')->plural($classes).' ('.$result['grades'].' '.str('grade')->plural($result['grades']).') as reviewed. They are selected below; choose Approve selected to approve them.';
        if ($result['stale'] > 0) {
            $message .= ' '.$result['stale'].' '.($result['stale'] === 1 ? 'grade' : 'grades').' changed or left the queue after the page loaded and '.($result['stale'] === 1 ? 'was' : 'were').' not included.';
        }

        return $this->backToList()->with('status', $message)->with('grading_preselect', array_keys($result['snapshots']));
    }

    public function approveClasses(ApproveClassesRequest $request, ApproveReviewedClassesAction $approve): RedirectResponse
    {
        $result = $approve->execute($request->validated('classes'), $request->session()->get(self::REVIEWED, []), $request->user());
        foreach ($result['consumedKeys'] as $key) {
            $request->session()->forget(self::REVIEWED.'.'.$key);
        }

        $message = $result['approved'] > 0
            ? 'Approved '.$result['approved'].' final '.str('grade')->plural($result['approved']).' in '.$result['classes'].' '.str('class')->plural($result['classes']).'.'
            : 'No grades were approved.';
        if ($result['changed'] > 0) {
            $message .= ' '.$result['changed'].' '.($result['changed'] === 1 ? 'grade was' : 'grades were').' skipped because '.($result['changed'] === 1 ? 'it' : 'they').' changed after you opened the class. Open it again to review.';
        }
        if ($result['notReviewed'] > 0) {
            $message .= ' '.$result['notReviewed'].' selected '.str('class')->plural($result['notReviewed']).' still '.($result['notReviewed'] === 1 ? 'needs' : 'need').' to be opened and reviewed first.';
        }

        return $this->backToList()->with('status', $message);
    }

    public function finalize(Request $request, StudentSubjectGrade $grade, FinalizeGradeAction $action): RedirectResponse
    {
        Gate::authorize('finalize', $grade);
        $action->execute($grade, $request->user());

        return back()->with('status', 'Final grade approved and released to the family portal.');
    }

    public function approveClass(ApproveGradesRequest $request, ApproveGradesAction $approve): RedirectResponse
    {
        $result = $approve->execute($request->validated('grade_ids'), $request->user());

        return back()->with('status', $result['approved'].' final '.str('grade')->plural($result['approved']).' approved'
            .($result['skipped'] > 0 ? '; '.$result['skipped'].' skipped because they changed or are no longer waiting.' : '.'));
    }

    public function review(ReviewGradeCorrectionRequest $request, GradeCorrectionRequest $correction, ReviewGradeCorrectionAction $action): RedirectResponse
    {
        Gate::authorize('finalize', $correction->grade);
        $data = $request->validated();
        $action->execute($correction, $request->user(), $data['decision'], $data['review_note']);

        return back()->with('status', $data['decision'] === 'approve' ? 'Grade reopened for teacher correction.' : 'Correction request rejected.');
    }

    /** Back to the same filtered Grade approval page, scrolled to the waiting list (never off-site). */
    private function backToList(): RedirectResponse
    {
        $index = route('grading.admin.index');
        $previous = strtok(url()->previous(), '#');
        $target = is_string($previous) && strtok($previous, '?') === $index ? $previous : $index;

        return redirect()->to($target.'#waiting-list');
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @return LengthAwarePaginator<int, mixed>
     */
    private function paginate(Collection $items, int $perPage, Request $request): LengthAwarePaginator
    {
        $current = max(1, (int) $request->query('page', 1));

        return (new LengthAwarePaginator($items->forPage($current, $perPage)->values(), $items->count(), $perPage, $current, [
            'path' => $request->url(),
            'pageName' => 'page',
        ]))->withQueryString();
    }
}
