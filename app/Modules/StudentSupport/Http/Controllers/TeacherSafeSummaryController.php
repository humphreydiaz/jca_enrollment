<?php

namespace App\Modules\StudentSupport\Http\Controllers;

use App\Modules\People\Models\StudentProfile;
use App\Modules\StudentSupport\Actions\ViewTeacherSafeSummaryAction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherSafeSummaryController
{
    public function __invoke(string $student, Request $request, ViewTeacherSafeSummaryAction $view): View
    {
        $profile = StudentProfile::query()->where('public_id', $student)->firstOrFail();
        $safeView = $view->execute($request->user(), $profile);
        abort_if($safeView === null, 404);

        return view('support.teacher-safe-summary', ['student' => $profile, 'safeView' => $safeView]);
    }
}
