<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentToFollowApproval extends Model
{
    protected $fillable = ['fee_assessment_id', 'approved_until', 'reason', 'requested_by', 'approved_by', 'is_extension', 'approved_at'];

    protected function casts(): array
    {
        return ['approved_until' => 'date', 'is_extension' => 'boolean', 'approved_at' => 'datetime'];
    }
}
