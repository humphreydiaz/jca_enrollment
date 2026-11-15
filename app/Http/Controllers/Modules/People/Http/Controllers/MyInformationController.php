<?php

namespace App\Http\Controllers\Modules\People\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Models\StudentProfile;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MyInformationController extends Controller
{
    private const STUDENT_FIELDS = [
        'birth_place', 'mother_tongue', 'current_address', 'permanent_address',
        'mobile', 'father_first_name', 'father_middle_name', 'father_last_name',
        'father_extension_name', 'mother_first_name', 'mother_middle_name',
        'mother_maiden_last_name', 'mother_extension_name',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->hasRole('student') || $user->hasRole('guardian'), 403);

        $students = $this->accessibleStudents($request);
        $selectedId = $request->integer('student');
        $student = $selectedId
            ? $students->firstWhere('id', $selectedId)
            : $students->first();

        if ($selectedId && ! $student) {
            abort(403);
        }

        $guardian = $user->guardianProfile;
        $enrollment = $student?->enrollments()->with('guardian')->latest('id')->first();

        return view('people.my-information.index', compact('student', 'students', 'guardian', 'enrollment'));
    }

    public function update(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole('student') || $user->hasRole('guardian'), 403);

        $students = $this->accessibleStudents($request);
        $student = $students->firstWhere('id', $request->integer('student_id'));
        abort_unless($student, 403);

        $rules = [
            'student_id' => ['required', 'integer'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'mother_tongue' => ['nullable', 'string', 'max:100'],
            'current_address' => ['nullable', 'string', 'max:1000'],
            'permanent_address' => ['nullable', 'string', 'max:1000'],
            'mobile' => ['nullable', 'string', 'max:30'],
        ];
        foreach (['father_first_name', 'father_middle_name', 'father_last_name',
            'mother_first_name', 'mother_middle_name', 'mother_maiden_last_name'] as $name) {
            $rules[$name] = ['nullable', 'string', 'max:100'];
        }
        foreach (['father_extension_name', 'mother_extension_name'] as $name) {
            $rules[$name] = ['nullable', 'string', 'max:30'];
        }
        if ($user->hasRole('guardian') && $user->guardianProfile) {
            $rules['guardian_mobile'] = ['nullable', 'string', 'max:30'];
            $rules['guardian_email'] = ['nullable', 'email', 'max:255'];
            $rules['preferred_contact_method'] = ['nullable', Rule::in(['sms', 'email', 'phone'])];
        }

        $validated = $request->validate($rules);
        $guardian = $user->hasRole('guardian') ? $user->guardianProfile : null;

        DB::transaction(function () use ($student, $guardian, $validated, $audit, $user): void {
            $before = $student->only(self::STUDENT_FIELDS);
            $student->fill(collect($validated)->only(self::STUDENT_FIELDS)->all());
            if ($student->isDirty()) {
                $student->save();
                $audit->recordChanges($user, 'people.student.self_profile_updated', $student,
                    $before, $student->only(self::STUDENT_FIELDS));
            }

            if ($guardian) {
                $fields = ['mobile', 'email', 'preferred_contact_method'];
                $changes = [];
                foreach ($fields as $field) {
                    $inputKey = $field === 'mobile' ? 'guardian_mobile' : ($field === 'email' ? 'guardian_email' : $field);
                    if (array_key_exists($inputKey, $validated)) {
                        $changes[$field] = $validated[$inputKey];
                    }
                }
                $beforeGuardian = $guardian->only($fields);
                $guardian->fill($changes);
                if ($guardian->isDirty()) {
                    $guardian->save();
                    $audit->recordChanges($user, 'people.guardian.self_profile_updated', $guardian,
                        $beforeGuardian, $guardian->only($fields));
                }
            }
        });

        return redirect()->route('my-information.index', ['student' => $student->id])
            ->with('status', 'Information updated successfully.');
    }

    private function accessibleStudents(Request $request): \Illuminate\Support\Collection
    {
        $user = $request->user();
        if ($user->hasRole('student')) {
            return $user->studentProfile ? collect([$user->studentProfile]) : collect();
        }

        // Only guardian-owned applications can be edited here. A verified link alone
        // does not grant the right to change a Grade 7–10 student's learner profile.
        $studentIds = Enrollment::query()
            ->where('access_issued_to_user_id', $user->id)
            ->whereNotNull('student_id')
            ->pluck('student_id')->unique();

        return StudentProfile::query()->whereIn('id', $studentIds)->orderBy('last_name')->get();
    }
}
