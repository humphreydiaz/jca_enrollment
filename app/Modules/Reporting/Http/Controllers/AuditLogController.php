<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Reporting\Http\Requests\AuditFilterRequest;
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
        $audit->record($request->user(), 'reporting.audit.index_viewed');

        return view('reporting.audit.index', ['logs' => $logs]);
    }

    public function show(AuditLog $auditLog, AuditFilterRequest $request, AuditRecorder $audit): View
    {
        Gate::authorize('view', $auditLog);
        $auditLog->load('actor');
        $audit->record($request->user(), 'reporting.audit.entry_viewed', $auditLog);

        return view('reporting.audit.show', ['log' => $auditLog]);
    }
}
