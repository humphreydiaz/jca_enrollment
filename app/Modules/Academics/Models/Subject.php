<?php

namespace App\Modules\Academics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Subject extends Model
{
    protected $fillable = ['code', 'name', 'description', 'is_active'];

    protected static function booted(): void
    {
        static::creating(fn (self $subject) => $subject->public_id ??= (string) Str::ulid());
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function sectionSubjects(): HasMany
    {
        return $this->hasMany(SectionSubject::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
