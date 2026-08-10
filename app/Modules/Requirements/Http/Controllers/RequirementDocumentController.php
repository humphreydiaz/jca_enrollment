<?php

namespace App\Modules\Requirements\Http\Controllers;

use App\Modules\Enrollment\Models\DocumentUpload;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\Requirements\Actions\RecordHardCopyReceiptAction;
use App\Modules\Requirements\Actions\ReviewRequirementAction;
use App\Modules\Requirements\Actions\ReviewSoftCopyAction;
use App\Modules\Requirements\Actions\StoreRequirementDocumentAction;
use App\Modules\Requirements\Http\Requests\UpdateHardCopyReceiptRequest;
use App\Modules\Requirements\Http\Requests\UploadRequirementDocumentRequest;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RequirementDocumentController
{
    public function store(UploadRequirementDocumentRequest $request, EnrollmentRequirement $requirement, StoreRequirementDocumentAction $store): RedirectResponse
    {
        $store->execute($requirement, $request->file('document'), $request->user());

        return back()->with('status', 'Soft copy uploaded privately for Registrar review.');
    }

    public function receipt(UpdateHardCopyReceiptRequest $request, EnrollmentRequirement $requirement, RecordHardCopyReceiptAction $recordReceipt): RedirectResponse
    {
        $data = $request->validated();
        $recordReceipt->execute($requirement, (bool) $data['received'], $request->user(), $data['receipt_notes'] ?? null);

        return $this->backToReview((bool) $data['received'] ? 'Hard-copy requirement marked received.' : 'Requirement marked not received.');
    }

    public function review(EnrollmentRequirement $requirement, Request $request, ReviewRequirementAction $review): RedirectResponse
    {
        Gate::authorize('review', EnrollmentRequirement::class);
        $data = $request->validate(['status' => ['required', 'in:verified,rejected,waived'], 'reason' => ['nullable', 'required_if:status,rejected,waived', 'string', 'max:2000']]);
        if ($data['status'] === 'waived') {
            Gate::authorize('waive', EnrollmentRequirement::class);
        }
        $review->execute($requirement, $data['status'], $request->user(), $data['reason'] ?? null);

        return $this->backToReview('Requirement reviewed.');
    }

    public function reviewSoftCopy(EnrollmentRequirement $requirement, Request $request, ReviewSoftCopyAction $review): RedirectResponse
    {
        Gate::authorize('review', EnrollmentRequirement::class);
        $data = $request->validate([
            'soft_copy_status' => ['required', 'in:verified,rejected'],
            'reason' => ['nullable', 'required_if:soft_copy_status,rejected', 'string', 'max:2000'],
        ]);
        $review->execute($requirement, $data['soft_copy_status'], $request->user(), $data['reason'] ?? null);

        return $this->backToReview('Soft-copy review recorded.');
    }

    /**
     * Redirect back to the page the reviewer came from, but never to a document
     * preview/download URL. Those files are loaded in an iframe, and that GET
     * request overwrites the session's "previous URL" — so a plain back() would
     * strand the reviewer on the raw file and swallow the success toast.
     */
    private function backToReview(string $status): RedirectResponse
    {
        $previous = url()->previous();
        $target = str_contains((string) parse_url($previous, PHP_URL_PATH), '/documents/')
            ? route('enrollment.registrar.index')
            : $previous;

        return redirect($target)->with('status', $status);
    }

    public function preview(DocumentUpload $document, Request $request, AuditRecorder $audit): StreamedResponse
    {
        $this->authorizeDocument($document, $request, $audit, 'preview');
        $disposition = HeaderUtils::makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $document->original_filename, 'requirement.'.$document->extension);

        return Storage::disk($document->disk)->response($document->path, $document->original_filename, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; frame-ancestors 'self'",
        ]);
    }

    public function download(DocumentUpload $document, Request $request, AuditRecorder $audit): StreamedResponse
    {
        $this->authorizeDocument($document, $request, $audit, 'download');

        return Storage::disk($document->disk)->download($document->path, $document->original_filename, ['Content-Type' => $document->mime_type, 'Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function authorizeDocument(DocumentUpload $document, Request $request, AuditRecorder $audit, string $action): void
    {
        $requirement = $document->requirement()->with(['enrollment.student', 'enrollment.guardian'])->firstOrFail();
        Gate::forUser($request->user())->authorize('view', $requirement);
        abort_if($document->archived_at !== null || $document->deleted_at !== null || ! Storage::disk($document->disk)->exists($document->path), 404);
        DB::table('document_access_logs')->insert(['document_upload_id' => $document->id, 'actor_user_id' => $request->user()->id, 'action' => $action, 'purpose' => 'enrollment requirement review', 'occurred_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $audit->record($request->user(), 'requirements.document.'.$action.'ed', $document);
    }
}
