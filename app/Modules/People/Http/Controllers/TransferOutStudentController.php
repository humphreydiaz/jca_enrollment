<?php

namespace App\Modules\People\Http\Controllers;

use App\Modules\People\Actions\RecordStudentExitAction;
use App\Modules\People\Http\Requests\TransferOutStudentRequest;
use App\Modules\People\Models\StudentProfile;
use App\Modules\People\Support\StudentExitOptions;
use Illuminate\Http\RedirectResponse;

class TransferOutStudentController
{
    public function __invoke(
        TransferOutStudentRequest $request,
        StudentProfile $student,
        RecordStudentExitAction $exit,
    ): RedirectResponse {
        $exit->execute($student, StudentExitOptions::TRANSFERRED_OUT, $request->validated(), $request->user(), 'transferOut');

        return redirect()
            ->route('people.registrar.students.show', $student)
            ->with('status', 'Learner marked as Transferred Out. The SF10 release was recorded and the record was moved to Archived records.');
    }
}
