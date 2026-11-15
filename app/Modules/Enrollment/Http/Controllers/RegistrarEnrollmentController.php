<?php

namespace App\Modules\Enrollment\Http\Controllers;

use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Enrollment\Actions\ReviewEnrollmentDuplicateAction;
use App\Modules\Enrollment\Actions\TransitionEnrollmentAction;
use App\Modules\Enrollment\Http\Requests\BulkTransitionEnrollmentRequest;
use App\Modules\Enrollment\Http\Requests\ReviewEnrollmentDuplicateRequest;
use App\Modules\Enrollment\Http\Requests\TransitionEnrollmentRequest;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\Enrollment\Support\EnrollmentApplicationCompleteness;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\StudentProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class RegistrarEnrollmentController
{
    public function index(
        EnrollmentApplicationCompleteness $completeness
    ): View {
        Gate::authorize('viewAny', Enrollment::class);

        $gradeId = request('grade')
            ? GradeLevel::query()
                ->where('public_id', request('grade'))
                ->value('id')
            : null;

        $query = Enrollment::query()->with([
            'student',
            'guardian',
            'accessOwner',
            'academicYear',
            'gradeLevel',
            'previousEnrollment.gradeLevel',
            'previousEnrollment.academicYear',
            'requirements.documentType',
            'requirements.receivedBy',
        
            'requirements.activeUpload',
            'requirements.carriedFromRequirement.enrollment.academicYear',
            'requirements.carriedFromRequirement.enrollment.gradeLevel',
            'potentialDuplicateStudent',
            'potentialDuplicateGuardian',
            'lateEnrollmentApprover',
        ]);

        if (request('status')) {
            $query->where('status', request('status'));
        }

        if ($gradeId !== null) {
            $query->where('grade_level_id', $gradeId);
        }

        if (request('late_status')) {
            $query->where(
                'is_late_enrollment',
                true
            )->where(
                'late_enrollment_status',
                request('late_status')
            );
        }

        if (request('search')) {
            $search = '%' . request('search') . '%';

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where(
                        'application_number',
                        'like',
                        $search
                    )
                    ->orWhereHas(
                        'student',
                        function ($student) use ($search) {
                            $student
                                ->where(
                                    'first_name',
                                    'like',
                                    $search
                                )
                                ->orWhere(
                                    'last_name',
                                    'like',
                                    $search
                                );
                        }
                    );
            });
        }

        $enrollments = $query
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $draftMissing = $enrollments
            ->getCollection()
            ->filter(
                fn (Enrollment $enrollment): bool =>
                    $enrollment->status === 'draft'
                    && $completeness->appliesTo($enrollment)
            )
            ->mapWithKeys(
                fn (Enrollment $enrollment): array => [
                    $enrollment->id =>
                        $completeness->missing($enrollment),
                ]
            )
            ->filter();

        $allowedTransitions = $enrollments
            ->getCollection()
            ->mapWithKeys(function (
                Enrollment $enrollment
            ) use ($draftMissing): array {

                $transitions =
                    TransitionEnrollmentAction::allowedTransitionsFrom(
                        $enrollment->status
                    );

                $transitions = array_filter(
                    $transitions,
                    function (string $status) use (
                        $enrollment,
                        $draftMissing
                    ): bool {

                        if (
                            $status === 'submitted'
                            && $draftMissing->has($enrollment->id)
                        ) {
                            return false;
                        }

                        if (
                            in_array(
                                $status,
                                ['submitted', 'approved', 'enrolled'],
                                true
                            )
                            && ! $enrollment->canProceedWithEnrollment()
                        ) {
                            return false;
                        }

                        return true;
                    }
                );

                return [
                    $enrollment->id =>
                        array_values($transitions),
                ];
            });

        $statusCounts = Enrollment::query()
            ->selectRaw(
                'status, count(*) as aggregate'
            )
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $lateCounts = Enrollment::query()
            ->where('is_late_enrollment', true)
            ->selectRaw(
                'late_enrollment_status, count(*) as aggregate'
            )
            ->groupBy('late_enrollment_status')
            ->pluck('aggregate', 'late_enrollment_status');

        $summary = [
            'total' => (int) $statusCounts->sum(),

            'needs_review' => (int) $statusCounts
                ->only([
                    'submitted',
                    'under_review',
                    'incomplete',
                    'assessment_required',
                    'assessment_pending',
                ])
                ->sum(),

            'waitlisted' => (int) (
                $statusCounts['waitlisted'] ?? 0
            ),

            'approved' => (int) (
                $statusCounts['approved'] ?? 0
            ),

            'late_pending' => (int) (
                $lateCounts['pending'] ?? 0
            ),

            'late_approved' => (int) (
                $lateCounts['approved'] ?? 0
            ),

            'late_rejected' => (int) (
                $lateCounts['rejected'] ?? 0
            ),
        ];

        $filterGrades = GradeLevel::query()
            ->where('is_active', true)
            ->orderBy('sequence')
            ->get();

        return view(
            'enrollment.registrar.index',
            compact(
                'enrollments',
                'allowedTransitions',
                'draftMissing',
                'summary',
                'filterGrades'
            )
        );
    }

    public function transition(
        Enrollment $enrollment,
        TransitionEnrollmentRequest $request,
        TransitionEnrollmentAction $transition
    ): RedirectResponse {

        $data = $request->validated();

        if (
            in_array(
                $data['status'],
                ['submitted', 'approved', 'enrolled'],
                true
            )
            && ! $enrollment->canProceedWithEnrollment()
        ) {
            throw ValidationException::withMessages([
                'status' =>
                    'Late enrollment approval is required before proceeding.',
            ]);
        }

        $transition->execute(
            $enrollment,
            $data['status'],
            $request->user(),
            $data['reason'] ?? ''
        );

        return back()->with(
            'status',
            'Enrollment status updated.'
        );
    }

    public function bulkTransition(
        BulkTransitionEnrollmentRequest $request,
        TransitionEnrollmentAction $transition
    ): RedirectResponse {

        Gate::authorize('viewAny', Enrollment::class);

        $data = $request->validated();

        $status = $data['status'];
        $reason = $data['reason'] ?? '';

        $applied = 0;
        $skipped = 0;

        foreach (
            $data['enrollment_public_ids'] as $publicId
        ) {
            $enrollment = Enrollment::query()
                ->where('public_id', $publicId)
                ->first();

            if (
                ! $enrollment
                || $request->user()->cannot(
                    'update',
                    $enrollment
                )
            ) {
                $skipped++;
                continue;
            }

            if (
                in_array(
                    $status,
                    ['submitted', 'approved', 'enrolled'],
                    true
                )
                && ! $enrollment->canProceedWithEnrollment()
            ) {
                $skipped++;
                continue;
            }

            try {
                $transition->execute(
                    $enrollment,
                    $status,
                    $request->user(),
                    $reason
                );

                $applied++;

            } catch (Throwable $exception) {
                $skipped++;
            }
        }

        $label = str($status)
            ->replace('_', ' ')
            ->headline();

        return back()->with(
            'status',
            "Applied \"{$label}\" to {$applied} application(s). {$skipped} skipped (not eligible for this transition)."
        );
    }

    public function reviewDuplicate(
        Enrollment $enrollment,
        ReviewEnrollmentDuplicateRequest $request,
        ReviewEnrollmentDuplicateAction $review
    ): RedirectResponse {

        $data = $request->validated();

        $student = isset($data['student_public_id'])
            ? StudentProfile::query()
                ->where(
                    'public_id',
                    $data['student_public_id']
                )
                ->firstOrFail()
            : null;

        $guardian = isset($data['guardian_public_id'])
            ? GuardianProfile::query()
                ->where(
                    'public_id',
                    $data['guardian_public_id']
                )
                ->firstOrFail()
            : null;

        $review->execute(
            $enrollment,
            $data['duplicate_decision'],
            $request->user(),
            $data['reason'],
            $student,
            $guardian
        );

        return back()->with(
            'status',
            'Duplicate review recorded.'
        );
    }

    public function reviewLateEnrollment(
        Enrollment $enrollment,
        Request $request
    ): RedirectResponse {

        Gate::authorize('update', $enrollment);

        $data = $request->validate([
            'decision' => [
                'required',
                'in:approved,rejected',
            ],
            'reason' => [
                'nullable',
                'required_if:decision,rejected',
                'string',
                'max:1000',
            ],
        ]);

        DB::transaction(function () use (
            $enrollment,
            $request,
            $data
        ): void {

            $record = Enrollment::query()
                ->whereKey($enrollment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                ! $record->is_late_enrollment
                || $record->late_enrollment_status !== 'pending'
            ) {
                throw ValidationException::withMessages([
                    'decision' =>
                        'Only pending late enrollment requests can be reviewed.',
                ]);
            }

            if ($record->isTerminal()) {
                throw ValidationException::withMessages([
                    'decision' =>
                        'This enrollment is already closed.',
                ]);
            }

            $approved = $data['decision'] === 'approved';

            $record->late_enrollment_status =
                $data['decision'];

            $record->late_enrollment_approved_by =
                $approved
                    ? $request->user()->getKey()
                    : null;

            $record->late_enrollment_approved_at =
                $approved
                    ? now()
                    : null;

            $record->late_enrollment_reason =
                $data['reason'] ?? null;

            $record->save();
        });

        return back()->with(
            'status',
            $data['decision'] === 'approved'
                ? 'Late enrollment approved successfully.'
                : 'Late enrollment request rejected.'
        );
    }
}
