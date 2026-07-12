<?php

namespace App\Modules\Communications\Http\Controllers;

use App\Models\User;
use App\Modules\Academics\Models\TeachingAssignment;
use App\Modules\Billing\Models\FeeAssessment;
use App\Modules\Billing\Models\Payment;
use App\Modules\Communications\Models\Notification;
use App\Modules\Communications\Queries\VisibleAnnouncementsQuery;
use App\Modules\Enrollment\Models\Enrollment;
use App\Modules\People\Models\GuardianStudent;
use App\Modules\SectionAssignment\Models\SectionAssignment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalDashboardController
{
    public function __invoke(Request $request, VisibleAnnouncementsQuery $announcements): View
    {
        $user = $request->user();

        return view('communications.portal.dashboard', ['cards' => $this->cards($user), 'announcements' => $announcements->forUser($user)->latest('published_at')->limit(4)->get()]);
    }

    /** @return list<array{label: string, value: int, detail: string, route: string}> */
    private function cards(User $user): array
    {
        $cards = [];
        if ($user->hasRole('admin')) {
            $cards[] = ['label' => 'Enrollment review', 'value' => Enrollment::query()->whereIn('status', ['submitted', 'under_review', 'incomplete'])->count(), 'detail' => 'Applications needing attention', 'route' => route('enrollment.registrar.index')];
            $cards[] = ['label' => 'SMS follow-up', 'value' => Notification::query()->where('status', 'failed')->count(), 'detail' => 'Failed deliveries', 'route' => route('communications.admin')];
        }
        if ($user->hasRole('registrar')) {
            $cards[] = ['label' => 'Applications', 'value' => Enrollment::query()->whereIn('status', ['submitted', 'under_review', 'incomplete', 'assessment_pending'])->count(), 'detail' => 'Within your review scope', 'route' => route('enrollment.registrar.index')];
        }
        if ($user->hasRole('accounting')) {
            $cards[] = ['label' => 'Billing work', 'value' => Payment::query()->where('status', 'draft')->count() + FeeAssessment::query()->where('status', 'draft')->count(), 'detail' => 'Draft assessments or collections', 'route' => route('billing.index')];
        }
        if ($user->hasRole('teacher')) {
            $sections = TeachingAssignment::query()->active()->forUser($user)->pluck('section_id');
            $cards[] = ['label' => 'My students', 'value' => SectionAssignment::query()->whereIn('section_id', $sections)->where('status', 'confirmed')->whereNull('ended_at')->count(), 'detail' => 'Students in active assigned classes', 'route' => route('academics.teacher.assignments')];
        }
        if ($user->hasRole('guardian')) {
            $studentIds = GuardianStudent::query()->where('guardian_id', $user->guardianProfile?->id)->whereNull('ended_at')->whereNotNull('verified_at')->where('may_manage_enrollment', true)->pluck('student_id');
            $cards[] = ['label' => 'My children', 'value' => $studentIds->count(), 'detail' => 'Linked records you may manage', 'route' => route('guardian.enrollment.progress')];
        }
        if ($user->hasRole('student')) {
            $cards[] = ['label' => 'My enrollment', 'value' => Enrollment::query()->where('student_id', $user->studentProfile?->id)->count(), 'detail' => 'Your current and prior records', 'route' => route('student.enrollment.progress')];
        }

        return $cards;
    }
}
