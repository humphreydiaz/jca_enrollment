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

class StoreRequirementDocumentAction
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function execute(EnrollmentRequirement $requirement, UploadedFile $file, ?User $actor): DocumentUpload
    {
        return DB::transaction(function () use ($requirement, $file, $actor): DocumentUpload {
            $record = EnrollmentRequirement::query()->lockForUpdate()->with('enrollment')->findOrFail($requirement->id);
            $version = (int) DocumentUpload::query()->where('enrollment_requirement_id', $record->id)->max('version') + 1;
            DocumentUpload::query()->where('enrollment_requirement_id', $record->id)->whereNull('archived_at')->update(['archived_at' => now()]);
            $extension = strtolower($file->getClientOriginalExtension());
            $stored = (string) Str::uuid().'.'.$extension;
            $path = 'enrollments/'.$record->enrollment->public_id.'/'.$stored;
            Storage::disk('documents')->putFileAs(dirname($path), $file, basename($path));
            $upload = DocumentUpload::query()->create(['enrollment_requirement_id' => $record->id, 'disk' => 'documents', 'path' => $path, 'original_filename' => $file->getClientOriginalName(), 'stored_filename' => $stored, 'mime_type' => (string) $file->getMimeType(), 'extension' => $extension, 'byte_size' => $file->getSize(), 'checksum' => hash_file('sha256', $file->getRealPath()), 'uploaded_by' => $actor?->id, 'version' => $version]);
            $record->update(['status' => 'submitted', 'reviewed_by' => null, 'reviewed_at' => null, 'review_reason' => null]);
            $this->audit->record($actor, 'requirements.document.uploaded', $upload, ['version' => $version]);

            return $upload;
        });
    }
}
