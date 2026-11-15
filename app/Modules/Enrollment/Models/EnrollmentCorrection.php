<?php

namespace App\Modules\Enrollment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EnrollmentCorrection extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'public_id', 'enrollment_id', 'actor_user_id', 'field_name',
        'before_value', 'after_value', 'reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $correction): void {
            $correction->public_id ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
