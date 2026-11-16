<?php

namespace App\Modules\People\Http\Controllers;

use App\Models\User;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Actions\ConnectGuardianToStudentAction;
use App\Modules\People\Actions\DeleteUnusedStudentProfileAction;
use App\Modules\People\Actions\DisconnectStudentAccountAction;
use App\Modules\People\Actions\GrantStudentAccountAction;
use App\Modules\People\Actions\LinkStudentAccountAction;
use App\Modules\People\Actions\VerifyGuardianStudentLinkAction;
use App\Modules\People\Http\Requests\ConnectSiblingGuardianRequest;
use App\Modules\People\Http\Requests\DeleteStudentProfileRequest;
use App\Modules\People\Http\Requests\DisconnectStudentAccountRequest;
use App\Modules\People\Http\Requests\FamilyLinksRequest;
use App\Modules\People\Http\Requests\GrantStudentAccountRequest;
use App\Modules\People\Http\Requests\LinkStudentAccountRequest;
use App\Modules\People\Models\GuardianProfile;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\People\Models\StudentProfile;
use App\Modules\People\Support\LearnerProfileOptions;
use App\Modules\People\Support\StudentAccountIdentityMatcher;
use App\Modules\People\Support\StudentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PeopleManagementController
{
    public function index(
        FamilyLinksRequest $request,
        StudentAccountIdentityMatcher $matcher
    ): View {
        $search = trim((string) ($request->validated('search') ?? ''));

        $matchStudent = function (Builder $query) use ($search): void {
            $like = '%'.$search.'%';

            $query->where(fn (Builder $sub) => $sub
                ->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('student_number', 'like', $like)
                ->orWhereRaw(
                    "CONCAT_WS(' ', first_name, middle_name, last_name) LIKE ?",
                    [$like]
                ));
        };

        $learners = StudentProfile::query()
            ->when($search !== '', $matchStudent)
            ->with([
                'user',
                'enrollments' => fn ($query) => $query
                    ->with(['gradeLevel', 'academicYear'])
                    ->latest('id'),
                'guardianLinks' => fn ($query) => $query
                    ->whereNull('ended_at')
                    ->with('guardian.user'),
            ])
            ->withCount([
                'guardianLinks',
                'potentialDuplicateEnrollments',
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(8, ['*'], 'learners_page')
            ->withQueryString();

        $links = GuardianStudent::query()
            ->whereNull('ended_at')
            ->when(
                $search !== '',
                fn (Builder $query) => $query->where(
                    fn (Builder $sub) => $sub
                        ->whereHas('student', $matchStudent)
                        ->orWhereHas(
                            'guardian',
                            fn (Builder $guardian) => $guardian
                                ->where('first_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%')
                        )
                )
            )
            ->with(['guardian.user', 'student'])
            ->orderByRaw('verified_at IS NOT NULL')
            ->latest('id')
            ->paginate(12, ['*'], 'links_page')
            ->withQueryString();

        $studentLogins = $request->user()->hasRole('admin')
            ? User::query()
                ->whereHas(
                    'roles',
                    fn ($query) => $query->where('code', 'student')
                )
                ->whereDoesntHave('studentProfile')
                ->whereIn('status', ['invited', 'active'])
                ->orderBy('name')
                ->get()
            : collect();

        $loginOptions = $learners->getCollection()->mapWithKeys(
            fn (StudentProfile $learner) => [
                $learner->id => $studentLogins
                    ->filter(
                        fn (User $account) => $matcher->matches(
                            $learner,
                            $account
                        )
                    )
                    ->values(),
            ]
        );

        // Include ALL registered guardians, with or without
        // an existing Parent Portal login.
        $availableGuardians = GuardianProfile::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        // D-067: siblings enrolled this school year and any confirmed sibling discount, per learner.
        $currentEnrollments = $learners->getCollection()->mapWithKeys(fn (StudentProfile $learner) => [
            $learner->id => $learner->enrollments->first(fn ($enrollment) => ! in_array($enrollment->status, Enrollment::TERMINAL_STATUSES, true)),
        ]);
        $siblingDiscounts = app(\App\Modules\Billing\Queries\SiblingDiscountQuery::class)->forEnrollments($currentEnrollments->filter()->values());

        return view('people.admin.index', [
            'currentEnrollments' => $currentEnrollments,
            'siblingDiscounts' => $siblingDiscounts,
            'loginOptions' => $loginOptions,
            'search' => $search,
            'learners' => $learners,
            'links' => $links,
            'relationships' => LearnerProfileOptions::GUARDIAN_RELATIONSHIPS,
            'availableGuardians' => $availableGuardians,
            'terminal' => Enrollment::TERMINAL_STATUSES,
        ]);
    }

    public function grantStudentAccount(
        StudentProfile $student,
        GrantStudentAccountRequest $request,
        GrantStudentAccountAction $grant
    ): RedirectResponse {
        $access = $grant->execute(
            $student,
            $request->validated('reason'),
            $request->user()
        );

        $enrollment = $student->enrollments()
            ->whereNotIn('status', Enrollment::TERMINAL_STATUSES)
            ->with(['gradeLevel', 'academicYear'])
            ->latest('id')
            ->first();

        return back()
            ->with(
                'status',
                'Student account granted to '.$student->full_name.
                '. Print the slip now. The parent account is unchanged.'
            )
            ->with('granted_access', [
                'learner_name' => $student->full_name,
                'grade_level' => $enrollment?->gradeLevel?->name,
                'school_year' => $enrollment?->academicYear?->name,
                'application_number' => null,
                'account_owner_name' => $student->full_name,
                'account_owner_type' => 'Student',
                'student_number' => $student->student_number,
                'temporary_password' => $access->temporaryPassword,
                'expires_at' => $access->expiresAt
                    ->timezone('Asia/Manila')
                    ->format('d M Y, g:i A'),
                'issued_by' => (string) $request->user()->name,
                'issued_at' => now()
                    ->timezone('Asia/Manila')
                    ->format('d M Y, g:i A'),
            ]);
    }

    public function connectSiblingGuardian(
        StudentProfile $student,
        ConnectSiblingGuardianRequest $request,
        ConnectGuardianToStudentAction $connect
    ): RedirectResponse {
        $data = $request->validated();

        $sibling = StudentProfile::query()
            ->where(
                'student_number',
                StudentNumber::normalize(
                    $data['sibling_student_number']
                )
            )
            ->first();

        $guardians = $sibling === null
            ? collect()
            : GuardianProfile::query()
                ->whereNotNull('user_id')
                ->whereHas(
                    'studentLinks',
                    fn (Builder $link) => $link
                        ->where('student_id', $sibling->id)
                        ->whereNull('ended_at')
                        ->whereNotNull('verified_at')
                )
                ->orderBy('last_name')
                ->get();

        $form = 'sibling-'.$student->public_id;

        if (
            $sibling === null ||
            $sibling->is($student) ||
            $guardians->isEmpty()
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'sibling_student_number' => $sibling === null
                        ? 'No learner has that Student ID.'
                        : (
                            $sibling->is($student)
                                ? 'Enter the other child Student ID.'
                                : 'No verified parent account was found for that child.'
                        ),
                ], $form);
        }

        $guardian = $guardians->count() === 1
            ? $guardians->first()
            : $guardians->firstWhere(
                'public_id',
                $data['guardian'] ?? null
            );

        if ($guardian === null) {
            return back()
                ->withInput()
                ->with(
                    'sibling_choices',
                    [
                        $student->public_id => $guardians
                            ->map(
                                fn (GuardianProfile $g) => [
                                    'id' => $g->public_id,
                                    'name' => $g->full_name,
                                ]
                            )
                            ->values()
                            ->all(),
                    ]
                )
                ->withErrors([
                    'guardian' => 'Choose which parent is present.',
                ], $form);
        }

        try {
            $connect->execute(
                $guardian,
                $student,
                $data['relationship_type'],
                $request->user()
            );
        } catch (ValidationException $exception) {
            throw $exception->errorBag($form);
        }

        return back()->with(
            'status',
            $guardian->full_name.
            ' is now linked to '.$student->full_name.'.'
        );
    }

    public function linkParentGuardian(
        StudentProfile $student,
        Request $request,
        ConnectGuardianToStudentAction $connect
    ): RedirectResponse {
        abort_unless(
            $request->user()?->hasAnyRole(['admin', 'registrar']),
            403
        );

        $data = $request->validate([
            'guardian_public_id' => [
                'required',
                'string',
                'max:100',
            ],
            'relationship_type' => [
                'required',
                Rule::in(
                    array_keys(
                        LearnerProfileOptions::GUARDIAN_RELATIONSHIPS
                    )
                ),
            ],
            'confirmed_in_person' => ['accepted'],
        ]);

        $enrollment = $student->enrollments()
            ->whereNotIn(
                'status',
                Enrollment::TERMINAL_STATUSES
            )
            ->with('gradeLevel')
            ->latest('id')
            ->first();

        if ($enrollment === null) {
            $enrollment = $student->enrollments()
                ->with('gradeLevel')
                ->latest('id')
                ->first();
        }

        if (! in_array(
            $enrollment?->gradeLevel?->code,
            ['G7', 'G8', 'G9', 'G10'],
            true
        )) {
            throw ValidationException::withMessages([
                'student' => 'This option is for Grade 7–10 only.',
            ]);
        }

        $guardian = GuardianProfile::query()
            ->where(
                'public_id',
                $data['guardian_public_id']
            )
            ->whereNotNull('user_id')
            ->first();

        if ($guardian === null) {
            throw ValidationException::withMessages([
                'guardian_public_id' =>
                    'The selected guardian has no portal account yet.',
            ]);
        }

        $connect->execute(
            $guardian,
            $student,
            $data['relationship_type'],
            $request->user()
        );

        return back()->with(
            'status',
            'Parent/Guardian account successfully linked to '.
            $student->full_name.'.'
        );
    }

    // Retained for compatibility with any existing direct-link route.
    public function linkParentGuardianByStudentNumber(
        Request $request,
        ConnectGuardianToStudentAction $connect
    ): RedirectResponse {
        abort_unless(
            $request->user()?->hasAnyRole(['admin', 'registrar']),
            403
        );

        $data = $request->validateWithBag('parent_link', [
            'student_number' => [
                'required',
                'string',
                'max:30',
            ],
            'guardian_public_id' => [
                'required',
                'string',
                'max:100',
            ],
            'relationship_type' => [
                'required',
                Rule::in(
                    array_keys(
                        LearnerProfileOptions::GUARDIAN_RELATIONSHIPS
                    )
                ),
            ],
            'confirmed_in_person' => ['accepted'],
        ]);

        try {
            $student = StudentProfile::query()
                ->where(
                    'student_number',
                    StudentNumber::normalize(
                        $data['student_number']
                    )
                )
                ->first();

            if ($student === null) {
                throw ValidationException::withMessages([
                    'student_number' => 'Student ID not found.',
                ]);
            }

            $enrollment = $student->enrollments()
                ->whereNotIn(
                    'status',
                    Enrollment::TERMINAL_STATUSES
                )
                ->with('gradeLevel')
                ->latest('id')
                ->first();

            if ($enrollment === null) {
                $enrollment = $student->enrollments()
                    ->with('gradeLevel')
                    ->latest('id')
                    ->first();
            }

            if (! in_array(
                $enrollment?->gradeLevel?->code,
                ['G7', 'G8', 'G9', 'G10'],
                true
            )) {
                throw ValidationException::withMessages([
                    'student_number' =>
                        'This option is only for Grade 7–10 learners.',
                ]);
            }

            $guardian = GuardianProfile::query()
                ->where(
                    'public_id',
                    $data['guardian_public_id']
                )
                ->whereNotNull('user_id')
                ->first();

            if ($guardian === null) {
                throw ValidationException::withMessages([
                    'guardian_public_id' =>
                        'Parent/Guardian account not found.',
                ]);
            }

            $connect->execute(
                $guardian,
                $student,
                $data['relationship_type'],
                $request->user()
            );

            return back()->with(
                'status',
                'Parent/Guardian successfully linked to '.
                $student->full_name.'.'
            );
        } catch (ValidationException $exception) {
            throw $exception->errorBag('parent_link');
        }
    }

    public function verify(
        GuardianProfile $guardian,
        StudentProfile $student,
        VerifyGuardianStudentLinkAction $verifyLink
    ): RedirectResponse {
        abort_unless(
            request()->user()?->hasAnyRole([
                'admin',
                'registrar',
            ]),
            403
        );

        $link = GuardianStudent::query()
            ->where('guardian_id', $guardian->id)
            ->where('student_id', $student->id)
            ->whereNull('ended_at')
            ->firstOrFail();

        $verifyLink->execute(
            $link,
            request()->user()
        );

        return back()->with(
            'status',
            'Relationship verified.'
        );
    }

    public function linkStudentAccount(
        StudentProfile $student,
        LinkStudentAccountRequest $request,
        LinkStudentAccountAction $linkStudentAccount
    ): RedirectResponse {
        $account = User::query()
            ->where(
                'public_id',
                $request->validated('user_public_id')
            )
            ->firstOrFail();

        $linkStudentAccount->execute(
            $student,
            $account,
            $request->user(),
            $request->validated('exception_reason')
        );

        return back()->with(
            'status',
            'Student account connected. The student portal is now available.'
        );
    }

    public function destroyStudent(
        StudentProfile $student,
        DeleteStudentProfileRequest $request,
        DeleteUnusedStudentProfileAction $deleteStudent
    ): RedirectResponse {
        $deleteStudent->execute(
            $student,
            $request->user(),
            $request->validated('reason')
        );

        return back()->with(
            'status',
            'Unused mistaken learner record removed. Enrollment and history were preserved.'
        );
    }

    public function disconnectStudentAccount(
        StudentProfile $student,
        DisconnectStudentAccountRequest $request,
        DisconnectStudentAccountAction $disconnectStudentAccount
    ): RedirectResponse {
        $disconnectStudentAccount->execute(
            $student,
            $request->user(),
            $request->validated('reason')
        );

        return back()->with(
            'status',
            'Student account disconnected. Learner records and history were preserved.'
        );
    }
}