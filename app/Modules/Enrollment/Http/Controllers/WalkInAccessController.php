<?php

namespace App\Modules\Enrollment\Http\Controllers;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Enrollment\Actions\IssuedWalkInAccess;
use App\Modules\Enrollment\Actions\IssueJuniorHighBothAccountsAction;
use App\Modules\Enrollment\Actions\IssueWalkInAccessAction;
use App\Modules\Enrollment\Actions\ReissueWalkInAccessAction;
use App\Modules\Enrollment\Http\Requests\IssueWalkInAccessRequest;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Support\LearnerProfileOptions;
use App\Modules\Requirements\Actions\EnsureOfficialRequirementRulesAction;
use App\Modules\Requirements\Models\RequirementRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WalkInAccessController
{
    public function create(EnsureOfficialRequirementRulesAction $ensureRules): View
    {
        $years = AcademicYear::query()
            ->whereIn('status', ['planned', 'open'])
            ->orderByDesc('starts_on')
            ->get();

        foreach ($years as $year) {
            $ensureRules->execute($year, request()->user());
        }

        $requirementOptions = RequirementRule::query()
            ->with('documentType')
            ->whereIn('academic_year_id', $years->pluck('id'))
            ->where(function ($query) {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', today());
            })
            ->where(function ($query) {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', today());
            })
            ->get()
            ->groupBy(fn ($rule) => (string) $rule->academic_year_id)
            ->map(fn ($byYear) => $byYear
                ->groupBy('student_category')
                ->map(fn ($byCategory) => $byCategory
                    ->map(fn ($rule) => [
                        'id' => (int) $rule->document_type_id,
                        'name' => $rule->documentType->name,
                        'required' => (bool) $rule->is_required,
                    ])
                    ->values()
                    ->all())
                ->all())
            ->all();

        return view('enrollment.registrar.issue-access', [
            'requirementOptions' => $requirementOptions,
            'years' => $years,

            'grades' => GradeLevel::query()
                ->where('is_active', true)
                ->orderBy('sequence')
                ->get(),

            'sexes' => LearnerProfileOptions::SEXES,

            'guardianRelationships' =>
                LearnerProfileOptions::GUARDIAN_RELATIONSHIPS,
        ]);
    }

    public function store(
        IssueWalkInAccessRequest $request,
        IssueWalkInAccessAction $issue,
        IssueJuniorHighBothAccountsAction $issueBoth
    ): RedirectResponse {
        $data = $request->validated();

        $grade = GradeLevel::query()
            ->findOrFail($data['grade_level_id']);

        $isJuniorHigh = in_array(
            $grade->code,
            ['G7', 'G8', 'G9', 'G10'],
            true
        );

        if ($isJuniorHigh) {
            $both = $issueBoth->execute(
                $data,
                $request->user()
            );

            $issued = $both['issued'];

            return redirect()
                ->route('enrollment.registrar.issue-access.create')
                ->with(
                    'status',
                    'Student and Parent accounts issued and automatically linked.'
                )
                ->with(
                    'issued_access',
                    $this->flashData($issued)
                )
                ->with('issued_both', [
                    'student_name' =>
                        $both['student']->full_name,

                    'student_number' =>
                        $both['student']->student_number,

                    'temporary_password' =>
                        $issued->temporaryPassword,

                    'parent_name' =>
                        $both['guardian']->full_name,

                    'parent_login' =>
                        $both['parent_login'],

                    'parent_temporary_password' =>
                        $both['parent_temporary_password'],

                    'parent_expires_at' =>
                        $both['parent_expires_at']
                            ? $both['parent_expires_at']
                                ->copy()
                                ->timezone('Asia/Manila')
                                ->format('d M Y, g:i A')
                            : null,

                    'parent_status' =>
                        $both['parent_status'],

                    'verified' =>
                        $both['relationship_verified'],
                ]);
        }

        // Preserve the Nursery-Grade 6 flow.
        $issued = $issue->execute(
            $data,
            $request->user()
        );

        return redirect()
            ->route('enrollment.registrar.issue-access.create')
            ->with(
                'status',
                'Walk-in identity verified and temporary access issued.'
            )
            ->with(
                'issued_access',
                $this->flashData($issued)
            );
    }

    public function reissue(
        Enrollment $enrollment,
        Request $request,
        ReissueWalkInAccessAction $reissue
    ): RedirectResponse {
        Gate::authorize('update', $enrollment);

        $issued = $reissue->execute(
            $enrollment,
            $request->user()
        );

        return redirect()
            ->route('enrollment.registrar.issue-access.create')
            ->with(
                'status',
                'Replacement temporary access issued.'
            )
            ->with(
                'issued_access',
                $this->flashData($issued)
            );
    }

    private function flashData(
        IssuedWalkInAccess $issued
    ): array {
        $issued->enrollment->loadMissing([
            'student',
            'gradeLevel',
            'academicYear',
        ]);

        return [
            'learner_name' =>
                $issued->enrollment->student->full_name,

            'grade_level' =>
                $issued->enrollment->gradeLevel->name,

            'school_year' =>
                $issued->enrollment->academicYear->name,

            'issued_by' =>
                (string) request()->user()?->name,

            'issued_at' =>
                now()
                    ->timezone('Asia/Manila')
                    ->format('d M Y, g:i A'),

            'student_number' =>
                $issued->studentNumber,

            'temporary_password' =>
                $issued->temporaryPassword,

            'account_owner_name' =>
                $issued->accountOwnerName,

            'account_owner_type' =>
                $issued->accountOwnerType,

            'expires_at' =>
                $issued->expiresAt
                    ->copy()
                    ->timezone('Asia/Manila')
                    ->format('d M Y, g:i A'),

            'application_number' =>
                $issued->enrollment->application_number,
        ];
    }
}
