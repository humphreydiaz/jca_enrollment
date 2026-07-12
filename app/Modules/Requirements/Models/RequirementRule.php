<?php

namespace App\Modules\Requirements\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequirementRule extends Model
{
    protected $fillable = ['academic_year_id', 'student_category', 'document_type_id', 'is_required', 'is_blocking', 'notes', 'effective_from', 'effective_until'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'is_blocking' => 'boolean', 'effective_from' => 'date', 'effective_until' => 'date'];
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
}
