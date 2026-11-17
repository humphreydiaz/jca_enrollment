<?php

namespace App\Modules\People\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StudentProfileCorrection extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'public_id',
        'student_id',
        'actor_user_id',
        'field_name',
        'before_value',
        'after_value',
        'reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $correction): void {
            $correction->public_id ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<StudentProfile, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
