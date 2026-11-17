<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Reporting\Http\Requests\AuditFilterRequest;
use App\Modules\Reporting\Http\Requests\InformationUpdatesFilterRequest;
use App\Modules\Reporting\Policies\AuditLogPolicy;
use App\Modules\Shared\Audit\AuditRecorder;
use App\Modules\Shared\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AuditLogController
{
    public function index(AuditFilterRequest $request, AuditRecorder $audit): View
    {
        Gate::authorize('viewAny', AuditLog::class);
        $query = AuditLog::query()->with('actor')->latest('occurred_at');
        if ($request->filled('action')) {
            $query->where('action', 'like', '%'.addcslashes($request->string('action')->toString(), '%_').'%');
        }
        if ($request->filled('actor')) {
            $actor = $request->string('actor')->toString();
            $query->whereHas('actor', fn (Builder $users) => $users->where('public_id', $actor));
        }
        if ($request->filled('subject_reference')) {
            $query->where('subject_public_id', $request->string('subject_reference')->toString());
        }
        if ($request->filled('date_from')) {
            $query->whereDate('occurred_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('occurred_at', '<=', $request->date('date_to'));
        }
        $logs = $query->paginate(30)->withQueryString();

        return view('reporting.audit.index', ['logs' => $logs]);
    }

    public function informationUpdates(InformationUpdatesFilterRequest $request): View
    {
        Gate::authorize('viewInformationUpdates', AuditLog::class);

        $account = $request->string('account')->toString();
        $actions = match ($account) {
            'student' => ['people.student.self_profile_updated'],
            'guardian' => ['people.guardian.self_profile_updated'],
            default => AuditLogPolicy::PROFILE_UPDATE_ACTIONS,
        };

        $query = AuditLog::query()->with('actor')->whereIn('action', $actions)->latest('occurred_at');
        if ($request->filled('name')) {
            $name = addcslashes($request->string('name')->toString(), '%_\\');
            $query->whereHas('actor', fn (Builder $users) => $users->where('name', 'like', "%{$name}%"));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('occurred_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('occurred_at', '<=', $request->date('date_to'));
        }

        return view('reporting.audit.information-updates', [
            'logs' => $query->paginate(30)->withQueryString(),
        ]);
    }

    public function show(AuditLog $auditLog, AuditFilterRequest $request, AuditRecorder $audit): View
    {
        Gate::authorize('view', $auditLog);
        $auditLog->load('actor');
        $audit->record($request->user(), 'reporting.audit.entry_viewed', $auditLog);

        return view('reporting.audit.show', ['log' => $auditLog]);
    }
}
