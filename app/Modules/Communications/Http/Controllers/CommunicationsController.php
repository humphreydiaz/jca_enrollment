<?php

namespace App\Modules\Communications\Http\Controllers;

use App\Modules\Communications\Actions\CreateAnnouncementAction;
use App\Modules\Communications\Actions\PublishAnnouncementAction;
use App\Modules\Communications\Actions\RetrySmsNotificationAction;
use App\Modules\Communications\Actions\SendAnnouncementSmsAction;
use App\Modules\Communications\Actions\SendDirectMessageAction;
use App\Modules\Communications\Http\Requests\SendDirectMessageRequest;
use App\Modules\Communications\Http\Requests\SmsAudienceRequest;
use App\Modules\Communications\Http\Requests\StoreAnnouncementRequest;
use App\Modules\Communications\Models\Announcement;
use App\Modules\Communications\Models\AnnouncementAudience;
use App\Modules\Communications\Models\DirectMessage;
use App\Modules\Communications\Models\Notification;
use App\Modules\Communications\Queries\SmsRecipientDirectory;
use App\Modules\Communications\Queries\VisibleAnnouncementsQuery;
use App\Modules\Communications\Support\SmsLength;
use App\Modules\Communications\Support\SmsRecipient;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Communications (D-052, Admin only): one announcement composer (portal / SMS / both, by role group),
 * texting picked people directly, and one sent history with delivery results and retry.
 */
class CommunicationsController
{
    private const SENT = ['accepted', 'logged'];

    public function index(Request $request, VisibleAnnouncementsQuery $announcements): View
    {
        Gate::authorize('viewAny', Announcement::class);
        $records = $request->user()->hasRole('admin')
            ? Announcement::query()->with('audiences')->where('delivery', '!=', 'sms')->latest()->paginate(20)
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

    public function admin(Request $request): View|JsonResponse
    {
        Gate::authorize('create', Announcement::class);

        // AJAX pagination of the sent history re-requests this page; return just that section.
        if ($request->wantsJson()) {
            return $this->sectionJson('history');
        }

        return view('communications.admin.index', [
            ...$this->historyData(),
            'roles' => AnnouncementAudience::ROLES,
            'deliveries' => Announcement::DELIVERIES,
            'creditsPerPart' => SmsLength::creditsPerPart(),
            'route' => SmsLength::route()->value,
            'smsStatus' => app(\App\Modules\Communications\Queries\SmsStatusQuery::class)->status(),
        ]);
    }

    public function store(StoreAnnouncementRequest $request, CreateAnnouncementAction $action): RedirectResponse|JsonResponse
    {
        $announcement = $action->execute($request->safe()->only(['title', 'body', 'delivery', 'sms_body', 'status']), $request->roles(), $request->user());

        return $this->done($request, $this->announcementMessage($announcement));
    }

    public function publish(Announcement $announcement, Request $request, PublishAnnouncementAction $action): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', Announcement::class);
        $published = $action->execute($announcement, $request->user());

        return $this->done($request, $this->announcementMessage($published));
    }

    /** How many people a text would reach, before anything is sent. */
    public function audiencePreview(SmsAudienceRequest $request, SmsRecipientDirectory $directory): JsonResponse
    {
        return response()->json($this->audienceSummary($directory, $request->roles(), (string) $request->validated('sms_body')));
    }

    public function draftAudiencePreview(Announcement $announcement, SmsRecipientDirectory $directory, SendAnnouncementSmsAction $sms): JsonResponse
    {
        Gate::authorize('create', Announcement::class);

        return response()->json([
            ...$this->audienceSummary($directory, $sms->roles($announcement), $announcement->sendsSms() ? (string) $announcement->sms_body : ''),
            'delivery' => Announcement::DELIVERIES[$announcement->delivery] ?? 'Portal only',
            'sends_sms' => $announcement->sendsSms(),
        ]);
    }

    /** People with a number on file, for "Message people". */
    public function people(SmsAudienceRequest $request, SmsRecipientDirectory $directory): JsonResponse
    {
        $people = $directory->search((string) $request->validated('search'), $request->validated('group'));

        return response()->json(['people' => $people->map(fn (SmsRecipient $person) => $person->toArray())->values()]);
    }

    public function sendDirect(SendDirectMessageRequest $request, SendDirectMessageAction $action): RedirectResponse|JsonResponse
    {
        $message = $action->execute($request->validated('recipients'), $request->validated('body'), $request->user());

        return $this->done($request, 'Text message queued to '.$message->recipient_count.' '.Str::plural('person', $message->recipient_count).'.', 'messages');
    }

    public function retry(Notification $notification, Request $request, RetrySmsNotificationAction $action): RedirectResponse|JsonResponse
    {
        Gate::authorize('retrySms', Announcement::class);
        $action->execute($notification, $request->user());

        return $this->done($request, 'SMS retry queued.');
    }

    /** Retry every failed text of one announcement or one direct message. */
    public function retryFailed(string $type, string $publicId, Request $request, RetrySmsNotificationAction $action): RedirectResponse|JsonResponse
    {
        Gate::authorize('retrySms', Announcement::class);
        $subject = match ($type) {
            'announcement' => Announcement::query()->where('public_id', $publicId)->firstOrFail(),
            'message' => DirectMessage::query()->where('public_id', $publicId)->firstOrFail(),
            default => abort(404),
        };
        $failed = $subject->notifications()->where('status', 'failed')->get();
        $failed->each(fn (Notification $notification) => $action->execute($notification, $request->user()));

        return $this->done($request, $failed->count().' failed '.Str::plural('text', $failed->count()).' queued again.', $type === 'message' ? 'messages' : 'announcements');
    }

    public function preview(Announcement $announcement): JsonResponse
    {
        Gate::authorize('create', Announcement::class);
        $announcement->load(['audiences', 'author']);

        return response()->json([
            'title' => $announcement->title,
            'body' => $announcement->body,
            'smsBody' => $announcement->sendsSms() ? $announcement->sms_body : null,
            'delivery' => Announcement::DELIVERIES[$announcement->delivery] ?? 'Portal only',
            'status' => $announcement->status,
            'statusLabel' => str($announcement->status)->headline()->toString(),
            'publishedAt' => $announcement->published_at?->timezone(config('app.timezone'))->format('M j, Y · g:i A'),
            'author' => $announcement->author?->name,
            'audiences' => $announcement->audiences->map(fn ($audience) => $audience->label())->values()->all(),
            'url' => $announcement->showsOnPortal() ? route('communications.show', $announcement) : null,
        ]);
    }

    /**
     * @param  list<string>  $roles
     * @return array<string, mixed>
     */
    private function audienceSummary(SmsRecipientDirectory $directory, array $roles, string $smsBody): array
    {
        $audience = $directory->forAudience($roles);
        $total = $audience['recipients']->count();

        return [
            'groups' => array_values($audience['groups']),
            'total' => $total,
            'parts' => SmsLength::parts($smsBody),
            'credits_per_part' => SmsLength::creditsPerPart(),
            'credits' => SmsLength::credits($smsBody, $total),
        ];
    }

    private function announcementMessage(Announcement $announcement): string
    {
        if ($announcement->status === 'draft') {
            return 'Draft saved. Publish it from the sent history when ready.';
        }
        $texts = $announcement->notifications()->count();
        $parts = [$announcement->showsOnPortal() ? 'Announcement published on the portal' : 'Announcement sent'];
        if ($announcement->sendsSms()) {
            $parts[] = $texts > 0 ? 'text queued to '.$texts.' '.Str::plural('person', $texts) : 'no one in the audience has opted in to SMS with a number on file';
        }

        return implode('; ', $parts).'.';
    }

    private function done(Request $request, string $message, string $tab = 'announcements'): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson()) {
            return $this->sectionJson('history', $message, $tab);
        }

        return to_route('communications.admin')->with('status', $message);
    }

    /** Render one section partial as JSON so the page can swap it in place (resources/js/app.js). */
    private function sectionJson(string $section, ?string $message = null, ?string $tab = null): JsonResponse
    {
        $payload = ['section' => $section, 'html' => view("communications.admin.partials.{$section}", [...$this->historyData(), 'tab' => $tab ?? request('tab', 'announcements')])->render()];
        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload);
    }

    /** @return array<string, mixed> */
    private function historyData(): array
    {
        $counts = fn (Builder $query) => $query->withCount([
            'notifications as sms_total',
            'notifications as sms_sent' => fn (Builder $q) => $q->whereIn('status', self::SENT),
            'notifications as sms_failed' => fn (Builder $q) => $q->where('status', 'failed'),
            'notifications as sms_waiting' => fn (Builder $q) => $q->whereIn('status', ['queued', 'disabled']),
        ]);

        return [
            'announcements' => $counts(Announcement::query()->with(['audiences', 'author']))->latest()->paginate(10, ['*'], 'page')->withPath(route('communications.admin'))->appends('tab', 'announcements'),
            'messages' => $counts(DirectMessage::query()->with(['sender', 'notifications.guardian', 'notifications.student', 'notifications.recipient']))->latest()->paginate(10, ['*'], 'messages_page')->withPath(route('communications.admin'))->appends('tab', 'messages'),
            'tab' => request('tab', 'announcements') === 'messages' ? 'messages' : 'announcements',
        ];
    }
}
