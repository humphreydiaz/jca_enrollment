<?php

namespace App\Modules\Requirements\Http\Controllers;

use App\Modules\Enrollment\Models\DocumentUpload;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\Requirements\Actions\ReviewRequirementAction;
use App\Modules\Requirements\Actions\StoreRequirementDocumentAction;
use App\Modules\Requirements\Http\Requests\UploadRequirementDocumentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequirementDocumentController
{
    public function store(UploadRequirementDocumentRequest $request, EnrollmentRequirement $requirement, StoreRequirementDocumentAction $store): RedirectResponse
    {
        $store->execute($requirement, $request->file('document'), $request->user());

        return back()->with('status', 'Document uploaded for review.');
    }

    public function review(EnrollmentRequirement $requirement, Request $request, ReviewRequirementAction $review): RedirectResponse
    {
        Gate::authorize('review', EnrollmentRequirement::class);
        $data = $request->validate(['status' => ['required', 'in:verified,rejected,waived'], 'reason' => ['nullable', 'string', 'max:2000']]);
        if ($data['status'] === 'waived') {
            Gate::authorize('waive', EnrollmentRequirement::class);
        }$review->execute($requirement, $data['status'], $request->user(), $data['reason'] ?? null);

        return back()->with('status', 'Requirement reviewed.');
    }

    public function download(DocumentUpload $document, Request $request): StreamedResponse
    {
        $requirement = $document->requirement()->with('enrollment')->firstOrFail();
        Gate::forUser($request->user())->authorize('view', $requirement);
        abort_if($document->archived_at !== null || $document->deleted_at !== null, 404);
        DB::table('document_access_logs')->insert(['document_upload_id' => $document->id, 'actor_user_id' => $request->user()->id, 'action' => 'download', 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return Storage::disk($document->disk)->download($document->path, $document->original_filename, ['Content-Type' => $document->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }
}
