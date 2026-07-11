<?php

namespace App\Modules\Enrollment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DocumentUpload extends Model
{
    protected $fillable = ['enrollment_requirement_id', 'disk', 'path', 'original_filename', 'stored_filename', 'mime_type', 'extension', 'byte_size', 'checksum', 'uploaded_by', 'validation_status', 'version'];

    protected static function booted(): void
    {
        static::creating(fn (self $record) => $record->public_id ??= (string) Str::ulid());
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(EnrollmentRequirement::class, 'enrollment_requirement_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
