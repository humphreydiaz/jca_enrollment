<?php

namespace App\Modules\Communications\Models;

use App\Models\User;
use App\Modules\People\Models\GuardianProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SmsConsent extends Model
{
    protected $fillable = ['guardian_id', 'purpose', 'status', 'source', 'effective_at', 'withdrawn_at', 'recorded_by'];

    protected static function booted(): void
    {
        static::creating(fn (self $consent) => $consent->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['effective_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(GuardianProfile::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
