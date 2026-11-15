<?php

namespace App\Modules\Requirements\Http\Controllers;

use App\Modules\Enrollment\Models\DocumentUpload;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\Requirements\Actions\RecordHardCopyReceiptAction;
use App\Modules\Requirements\Actions\ReviewRequirementAction;
use App\Modules\Requirements\Http\Requests\UpdateHardCopyReceiptRequest;
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
    /**
     * Record the receipt of a physical requirement.
     * Available to authorized Registrar/Admin personnel.
     */
    public function receipt(
        UpdateHardCopyReceiptRequest $request,
        EnrollmentRequirement $requirement,
        RecordHardCopyReceiptAction $recordReceipt
    ): RedirectResponse {
        $data = $request->validated();

        $recordReceipt->execute(
            $requirement,
            (bool) $data['received'],
            $request->user(),
            $data['receipt_notes'] ?? null
        );

        return $this->backToReview(
            $requirement,
            (bool) $data['received']
                ? 'Hard-copy requirement marked received.'
                : 'Requirement marked not received.'
        );
    }

    /**
     * Review a physical enrollment requirement.
     */
    public function review(
        EnrollmentRequirement $requirement,
        Request $request,
        ReviewRequirementAction $review
    ): RedirectResponse {
        Gate::authorize('review', EnrollmentRequirement::class);

        $data = $request->validate([
            'status' => [
                'required',
                'in:verified,rejected,waived',
            ],
            'reason' => [
                'nullable',
                'required_if:status,rejected,waived',
                'string',
                'max:2000',
            ],
        ]);

        if ($data['status'] === 'waived') {
            Gate::authorize('waive', EnrollmentRequirement::class);
        }

        $review->execute(
            $requirement,
            $data['status'],
            $request->user(),
            $data['reason'] ?? null
        );

        return $this->backToReview(
            $requirement,
            'Hard-copy requirement reviewed.'
        );
    }

    /**
     * Return to the Registrar Enrollment Review page.
     */
    private function backToReview(
        EnrollmentRequirement $requirement,
        string $status
    ): RedirectResponse {
        $previous = url()->previous();

        $target = str_contains(
            (string) parse_url($previous, PHP_URL_PATH),
            '/documents/'
        )
            ? route('enrollment.registrar.index')
            : $previous;

        return redirect($target)->with([
            'status' => $status,
            'review_enrollment' =>
                $requirement->enrollment->public_id,
            'review_requirement_id' =>
                $requirement->id,
        ]);
    }

    /**
     * Historical document preview.
     * Existing files remain preserved.
     */
    public function preview(
        DocumentUpload $document,
        Request $request,
        AuditRecorder $audit
    ): StreamedResponse {
        $this->authorizeDocument(
            $document,
            $request,
            $audit,
            'preview'
        );

        $disposition = HeaderUtils::makeDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $document->original_filename,
            'requirement.' . $document->extension
        );

        return Storage::disk($document->disk)->response(
            $document->path,
            $document->original_filename,
            [
                'Content-Type' => $document->mime_type,
                'Content-Disposition' => $disposition,
                'Cache-Control' =>
                    'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' =>
                    "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; frame-ancestors 'self'",
            ]
        );
    }

    /**
     * Historical document download.
     */
    public function download(
        DocumentUpload $document,
        Request $request,
        AuditRecorder $audit
    ): StreamedResponse {
        $this->authorizeDocument(
            $document,
            $request,
            $audit,
            'download'
        );

        return Storage::disk($document->disk)->download(
            $document->path,
            $document->original_filename,
            [
                'Content-Type' => $document->mime_type,
                'Cache-Control' =>
                    'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    /**
     * Restrict access to authorized historical documents.
     */
    private function authorizeDocument(
        DocumentUpload $document,
        Request $request,
        AuditRecorder $audit,
        string $action
    ): void {
        $requirement = $document->requirement()
            ->with([
                'enrollment.student',
                'enrollment.guardian',
            ])
            ->firstOrFail();

        Gate::forUser($request->user())
            ->authorize('view', $requirement);

        abort_if(
            $document->archived_at !== null ||
            $document->deleted_at !== null ||
            ! Storage::disk($document->disk)
                ->exists($document->path),
            404
        );

        DB::table('document_access_logs')->insert([
            'document_upload_id' => $document->id,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'purpose' => 'historical enrollment requirement access',
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $audit->record(
            $request->user(),
            'requirements.document.' . $action . 'ed',
            $document
        );
    }
}
