<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentEvidence extends Model
{
    protected $table = 'payment_evidence';

    protected $fillable = ['payment_id', 'disk', 'storage_path', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'uploaded_by'];

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->public_id ??= (string) Str::ulid());
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
