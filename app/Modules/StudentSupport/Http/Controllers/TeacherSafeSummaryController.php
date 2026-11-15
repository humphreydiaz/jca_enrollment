<?php

namespace App\Modules\StudentSupport\Http\Controllers;

use App\Modules\People\Models\StudentProfile;
use App\Modules\StudentSupport\Actions\ViewTeacherSafeSummaryAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TeacherSafeSummaryController
{
    /**
     * The My teaching page loads this into a dialog (XHR → body only); a direct visit
     * or a browser without JavaScript gets the full page. Both paths go through the
     * same access check and access log, and neither response is cached.
     */
    public function __invoke(string $student, Request $request, ViewTeacherSafeSummaryAction $view): Response
    {
        $profile = StudentProfile::query()->where('public_id', $student)->firstOrFail();
        $safeView = $view->execute($request->user(), $profile);
        abort_if($safeView === null, 404);

        $template = $request->ajax() ? 'support.partials.teacher-safe-summary' : 'support.teacher-safe-summary';

        return response()
            ->view($template, ['student' => $profile, 'safeView' => $safeView])
            ->header('Cache-Control', 'no-store, private');
    }
}
