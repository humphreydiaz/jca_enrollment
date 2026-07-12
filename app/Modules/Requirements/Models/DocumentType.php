<?php

namespace App\Modules\Requirements\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    protected $fillable = ['code', 'name', 'sensitivity_class', 'retention_policy_key', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
