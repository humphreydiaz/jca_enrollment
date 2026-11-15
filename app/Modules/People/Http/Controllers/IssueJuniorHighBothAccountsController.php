<?php

namespace App\Modules\People\Http\Controllers;

use App\Modules\Academics\Models\AcademicYear;
use App\Modules\Academics\Models\GradeLevel;
use App\Modules\Enrollment\Actions\IssueJuniorHighBothAccountsAction;
use App\Modules\People\Support\LearnerProfileOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IssueJuniorHighBothAccountsController
{
    public function create(): View
    {
        abort_unless(
            auth()->user()?->hasAnyRole(['admin', 'registrar']),
            403
        );

        return view('people.admin.issue-both', [
            'years' => AcademicYear::query()->orderByDesc('id')->get(),
            'grades' => GradeLevel::query()
                ->whereIn('code', ['G7', 'G8', 'G9', 'G10'])
                ->get(),
            'relationships' => LearnerProfileOptions::GUARDIAN_RELATIONSHIPS,
        ]);
    }

    public function store(
        Request $request,
        IssueJuniorHighBothAccountsAction $action
    ): RedirectResponse {
        abort_unless(
            $request->user()?->hasAnyRole(['admin', 'registrar']),
            403
        );

        $data = $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'grade_level_id' => ['required', 'exists:grade_levels,id'],
            'student_category' => ['required', 'string'],
            'student_first_name' => ['required', 'string', 'max:100'],
            'student_middle_name' => ['nullable', 'string', 'max:100'],
            'student_last_name' => ['required', 'string', 'max:100'],
            'extension_name' => ['nullable', 'string', 'max:30'],
            'lrn' => ['nullable', 'digits:12'],
            'sex' => ['required', 'string'],
            'birth_date' => ['required', 'date', 'before:today'],
            'guardian_first_name' => ['required', 'string', 'max:100'],
            'guardian_middle_name' => ['nullable', 'string', 'max:100'],
            'guardian_last_name' => ['required', 'string', 'max:100'],
            'guardian_extension_name' => ['nullable', 'string', 'max:30'],
            'guardian_relationship' => [
                'required',
                Rule::in(array_keys(
                    LearnerProfileOptions::GUARDIAN_RELATIONSHIPS
                )),
            ],
            'guardian_mobile' => ['required', 'string', 'max:30'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'identity_confirmed' => ['required', 'accepted'],
        ]);

        $result = $action->execute($data, $request->user());

        return redirect()
            ->route('people.family.issue-both.create')
            ->with('issued_both', [
                'student_name' => $result['student']->full_name,
                'student_number' => $result['student']->student_number,
                'temporary_password' => $result['issued']->temporaryPassword,
                'parent_name' => $result['guardian']->full_name,
                'parent_status' => $result['parent_status'],
                'parent_setup_url' => $result['parent_setup_url'],
                'verified' => $result['relationship_verified'],
            ]);
    }
}
