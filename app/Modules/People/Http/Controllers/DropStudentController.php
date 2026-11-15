<?php

namespace App\Modules\People\Http\Controllers;

use App\Modules\People\Actions\RecordStudentExitAction;
use App\Modules\People\Http\Requests\DropStudentRequest;
use App\Modules\People\Models\StudentProfile;
use App\Modules\People\Support\StudentExitOptions;
use Illuminate\Http\RedirectResponse;

class DropStudentController
{
    public function __invoke(
        DropStudentRequest $request,
        StudentProfile $student,
        RecordStudentExitAction $exit,
    ): RedirectResponse {
        $exit->execute($student, StudentExitOptions::DROPPED, $request->validated(), $request->user(), 'dropStudent');

        return redirect()
            ->route('people.registrar.students.show', $student)
            ->with('status', 'Learner marked as Dropped. The record was moved to Archived records.');
    }
}
