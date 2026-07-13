<?php

namespace App\Modules\Reporting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ExportJob extends Model
{
    protected $fillable = ['requested_by', 'report_code', 'filter_snapshot', 'field_snapshot', 'status', 'disk', 'storage_path', 'download_name', 'row_count', 'checksum_sha256', 'failure_code', 'generated_at', 'expires_at', 'download_count', 'last_downloaded_at', 'deleted_at', 'deleted_by'];

    protected static function booted(): void
    {
        static::creating(fn (self $export) => $export->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return [
            'filter_snapshot' => 'array',
            'field_snapshot' => 'array',
            'generated_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_downloaded_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
