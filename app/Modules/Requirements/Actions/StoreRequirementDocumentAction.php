<?php

namespace App\Modules\Requirements\Actions;

use App\Models\User;
use App\Modules\Enrollment\Models\DocumentUpload;
use App\Modules\Enrollment\Models\EnrollmentRequirement;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreRequirementDocumentAction
{
    private const UPLOADABLE_ENROLLMENT_STATUSES = ['submitted', 'under_review', 'incomplete', 'assessment_required', 'assessment_pending', 'waitlisted'];

    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(EnrollmentRequirement $requirement, UploadedFile $file, User $actor): DocumentUpload
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedFilename = (string) Str::uuid().'.'.$extension;
        $path = 'enrollments/'.$requirement->enrollment->public_id.'/'.$storedFilename;

        if (! Storage::disk('documents')->putFileAs(dirname($path), $file, basename($path))) {
            throw ValidationException::withMessages(['document' => 'The soft copy could not be stored. Please try again.']);
        }

        try {
            return DB::transaction(function () use ($requirement, $file, $actor, $extension, $storedFilename, $path): DocumentUpload {
                $record = EnrollmentRequirement::query()->lockForUpdate()->with('enrollment')->findOrFail($requirement->id);
                if (! in_array($record->enrollment->status, self::UPLOADABLE_ENROLLMENT_STATUSES, true)) {
                    throw ValidationException::withMessages(['document' => 'Soft copies may be changed only while the application is being reviewed.']);
                }

                $version = (int) DocumentUpload::query()->where('enrollment_requirement_id', $record->id)->max('version') + 1;
                DocumentUpload::query()->where('enrollment_requirement_id', $record->id)->whereNull('archived_at')->update(['archived_at' => now()]);

                $upload = DocumentUpload::query()->create([
                    'enrollment_requirement_id' => $record->id,
                    'disk' => 'documents',
                    'path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_filename' => $storedFilename,
                    'mime_type' => (string) $file->getMimeType(),
                    'extension' => $extension,
                    'byte_size' => $file->getSize(),
                    'checksum' => hash_file('sha256', $file->getRealPath()),
                    'uploaded_by' => $actor->id,
                    'validation_status' => 'accepted',
                    'version' => $version,
                ]);

                $record->update([
                    'soft_copy_status' => 'submitted',
                    'soft_copy_reviewed_by' => null,
                    'soft_copy_reviewed_at' => null,
                    'soft_copy_review_reason' => null,
                ]);
                $this->audit->record($actor, 'requirements.soft_copy.uploaded', $upload, ['version' => $version]);

                return $upload;
            });
        } catch (\Throwable $exception) {
            Storage::disk('documents')->delete($path);
            throw $exception;
        }
    }
}
