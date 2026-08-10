<?php

namespace App\Modules\Communications\Http\Controllers;

use App\Modules\Communications\Actions\CreateAnnouncementAction;
use App\Modules\Communications\Actions\CreateNotificationTemplateVersionAction;
use App\Modules\Communications\Actions\RetrySmsNotificationAction;
use App\Modules\Communications\Http\Requests\StoreAnnouncementRequest;
use App\Modules\Communications\Http\Requests\StoreNotificationTemplateRequest;
use App\Modules\Communications\Models\Announcement;
use App\Modules\Communications\Models\Notification;
use App\Modules\Communications\Models\NotificationTemplate;
use App\Modules\Communications\Queries\VisibleAnnouncementsQuery;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CommunicationsController
{
    public function index(Request $request, VisibleAnnouncementsQuery $announcements): View
    {
        Gate::authorize('viewAny', Announcement::class);
        $records = $request->user()->hasRole('admin')
            ? Announcement::query()->with('audiences')->latest()->paginate(20)
            : $announcements->forUser($request->user())->latest('published_at')->paginate(20);

        return view('communications.announcements.index', ['announcements' => $records]);
    }

    public function show(Announcement $announcement, Request $request, AuditRecorder $audit): View
    {
        if (! Gate::allows('view', $announcement)) {
            abort(404);
        }
        $audit->record($request->user(), 'communications.announcement.viewed', $announcement);

        return view('communications.announcements.show', ['announcement' => $announcement]);
    }

    public function store(StoreAnnouncementRequest $request, CreateAnnouncementAction $action): RedirectResponse
    {
        $announcement = $action->execute($request->safe()->except('audiences'), $request->validated('audiences'), $request->user());

        return to_route('communications.show', $announcement)->with('status', 'Announcement saved.');
    }

    public function storeTemplate(StoreNotificationTemplateRequest $request, CreateNotificationTemplateVersionAction $action): RedirectResponse
    {
        $action->execute([
            ...$request->safe()->only(['code', 'purpose', 'content']),
            'is_active' => $request->boolean('is_active'),
        ], $request->user());

        return to_route('communications.admin')->with('status', 'Template version saved.');
    }

    public function admin(): View
    {
        Gate::authorize('create', Announcement::class);

        return view('communications.admin.index', [
            'templates' => NotificationTemplate::query()->latest()->get(),
            'failedNotifications' => Notification::query()->with(['guardian', 'message'])->where('status', 'failed')->latest('failed_at')->get(),
        ]);
    }

    public function retry(Notification $notification, Request $request, RetrySmsNotificationAction $action): RedirectResponse
    {
        Gate::authorize('retrySms', Announcement::class);
        $action->execute($notification, $request->user());

        return back()->with('status', 'SMS retry queued.');
    }
}
