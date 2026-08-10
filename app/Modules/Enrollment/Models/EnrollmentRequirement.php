<?php

namespace App\Modules\Enrollment\Models;

use App\Models\User;
use App\Modules\Requirements\Models\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EnrollmentRequirement extends Model
{
    protected $fillable = ['enrollment_id', 'document_type_id', 'carried_from_requirement_id', 'carried_over_by', 'carried_over_at', 'rule_snapshot', 'status', 'received_by', 'received_at', 'receipt_notes', 'soft_copy_status', 'soft_copy_reviewed_by', 'soft_copy_reviewed_at', 'soft_copy_review_reason', 'reviewed_by', 'reviewed_at', 'review_reason', 'waived_by'];

    protected function casts(): array
    {
        return ['rule_snapshot' => 'array', 'carried_over_at' => 'datetime', 'received_at' => 'datetime', 'soft_copy_reviewed_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function carriedFromRequirement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'carried_from_requirement_id');
    }

    public function carriedOverBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'carried_over_by');
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(DocumentUpload::class);
    }

    public function activeUpload(): HasOne
    {
        return $this->hasOne(DocumentUpload::class)->whereNull('archived_at')->whereNull('deleted_at')->latestOfMany('version');
    }

    public function softCopyReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'soft_copy_reviewed_by');
    }
}
