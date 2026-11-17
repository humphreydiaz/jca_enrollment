<?php

namespace App\Modules\Requirements\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    public const SCHOOL_FORM_9_CODE = 'FORM_138';

    public const SCHOOL_FORM_9_NAME = 'School Form 9 (SF9)';

    protected $fillable = ['code', 'name', 'sensitivity_class', 'retention_policy_key', 'is_active'];

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): string => ($this->attributes['code'] ?? null) === self::SCHOOL_FORM_9_CODE
                ? self::SCHOOL_FORM_9_NAME
                : $value,
        );
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
