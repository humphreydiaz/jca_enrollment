<?php

namespace App\Modules\Communications\Http\Controllers;

use App\Modules\Communications\Actions\CreateAnnouncementAction;
use App\Modules\Communications\Actions\CreateNotificationTemplateVersionAction;
use App\Modules\Communications\Actions\ManageNotificationTemplateAction;
use App\Modules\Communications\Actions\PublishAnnouncementAction;
use App\Modules\Communications\Actions\RetrySmsNotificationAction;
use App\Modules\Communications\Http\Requests\StoreAnnouncementRequest;
use App\Modules\Communications\Http\Requests\StoreNotificationTemplateRequest;
use App\Modules\Communications\Models\Announcement;
use App\Modules\Communications\Models\Notification;
use App\Modules\Communications\Models\NotificationTemplate;
use App\Modules\Communications\Queries\VisibleAnnouncementsQuery;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\JsonResponse;
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

    public function store(StoreAnnouncementRequest $request, CreateAnnouncementAction $action): RedirectResponse|JsonResponse
    {
        $announcement = $action->execute($request->safe()->except('audiences'), $request->validated('audiences'), $request->user());

        if ($request->wantsJson()) {
            return $this->sectionJson('announcements', 'Announcement saved.');
        }

        return to_route('communications.show', $announcement)->with('status', 'Announcement saved.');
    }

    public function storeTemplate(StoreNotificationTemplateRequest $request, CreateNotificationTemplateVersionAction $action): RedirectResponse|JsonResponse
    {
        $action->execute([
            ...$request->safe()->only(['code', 'purpose', 'content']),
            'is_active' => $request->boolean('is_active'),
        ], $request->user());

        if ($request->wantsJson()) {
            return $this->sectionJson('templates', 'Template version saved.');
        }

        return to_route('communications.admin')->with('status', 'Template version saved.');
    }

    public function setTemplateStatus(NotificationTemplate $template, Request $request, ManageNotificationTemplateAction $action): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', Announcement::class);
        $action->setActive($template, $request->boolean('is_active'), $request->user());

        $message = $request->boolean('is_active') ? 'Template version activated.' : 'Template version deactivated.';
        if ($request->wantsJson()) {
            return $this->sectionJson('templates', $message);
        }

        return back()->with('status', $message);
    }

    public function deleteTemplate(NotificationTemplate $template, Request $request, ManageNotificationTemplateAction $action): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', Announcement::class);
        $action->delete($template, $request->user());

        if ($request->wantsJson()) {
            return $this->sectionJson('templates', 'Template version deleted.');
        }

        return back()->with('status', 'Template version deleted.');
    }

    public function admin(Request $request): View|JsonResponse
    {
        Gate::authorize('create', Announcement::class);

        // AJAX pagination of the "Posted announcements" table re-requests this
        // route with ?page=N and Accept: application/json; return just the
        // re-rendered table section so the page can swap it without a reload.
        if ($request->wantsJson()) {
            return $this->sectionJson('announcements');
        }

        return view('communications.admin.index', $this->communicationsData());
    }

    public function preview(Announcement $announcement): JsonResponse
    {
        Gate::authorize('create', Announcement::class);
        $announcement->load(['audiences', 'author']);

        return response()->json([
            'title' => $announcement->title,
            'body' => $announcement->body,
            'status' => $announcement->status,
            'statusLabel' => str($announcement->status)->headline()->toString(),
            'publishedAt' => $announcement->published_at?->timezone(config('app.timezone'))->format('M j, Y · g:i A'),
            'author' => $announcement->author?->name,
            'audiences' => $announcement->audiences->map(fn ($audience) => $audience->label())->values()->all(),
            'url' => route('communications.show', $announcement),
        ]);
    }

    public function publish(Announcement $announcement, Request $request, PublishAnnouncementAction $action): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', Announcement::class);
        $action->execute($announcement, $request->input('sms_template_public_id'), $request->user());

        if ($request->wantsJson()) {
            return $this->sectionJson('announcements', 'Announcement published.');
        }

        return back()->with('status', 'Announcement published.');
    }

    public function retry(Notification $notification, Request $request, RetrySmsNotificationAction $action): RedirectResponse|JsonResponse
    {
        Gate::authorize('retrySms', Announcement::class);
        $action->execute($notification, $request->user());

        if ($request->wantsJson()) {
            return $this->sectionJson('failed-sms', 'SMS retry queued.');
        }

        return back()->with('status', 'SMS retry queued.');
    }

    /**
     * Render a single admin-workspace section partial as JSON so the browser
     * can swap it in place (see resources/js/app.js). The optional message
     * surfaces as a corner toast on success.
     */
    private function sectionJson(string $section, ?string $message = null): JsonResponse
    {
        $payload = [
            'section' => $section,
            'html' => view("communications.admin.partials.{$section}", $this->communicationsData())->render(),
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function communicationsData(): array
    {
        return [
            'announcements' => Announcement::query()->with(['audiences', 'author'])->latest()->paginate(10),
            'templates' => NotificationTemplate::query()->latest()->get(),
            'templateUsage' => Notification::query()->whereNotNull('notification_template_id')
                ->selectRaw('notification_template_id, count(*) as total')
                ->groupBy('notification_template_id')
                ->pluck('total', 'notification_template_id'),
            'failedNotifications' => Notification::query()->with(['guardian', 'message'])->where('status', 'failed')->latest('failed_at')->get(),
        ];
    }
}
