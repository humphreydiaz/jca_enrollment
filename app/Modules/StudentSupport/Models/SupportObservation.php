<?php

namespace App\Modules\StudentSupport\Models;

use Illuminate\Database\Eloquent\Model;

class SupportObservation extends Model
{
    protected $fillable = ['support_case_id', 'body', 'category', 'author_id', 'observed_at'];
    protected function casts(): array { return ['observed_at' => 'datetime']; }
}
