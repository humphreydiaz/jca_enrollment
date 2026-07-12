<?php

namespace App\Modules\Enrollment\Models;

use App\Modules\Requirements\Models\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EnrollmentRequirement extends Model
{
    protected $fillable = ['enrollment_id', 'document_type_id', 'rule_snapshot', 'status', 'reviewed_by', 'reviewed_at', 'review_reason', 'waived_by'];

    protected function casts(): array
    {
        return ['rule_snapshot' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(DocumentUpload::class);
    }
}
