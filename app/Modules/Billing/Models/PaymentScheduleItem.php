<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentScheduleItem extends Model
{
    protected $fillable = ['payment_plan_id', 'installment_number', 'label', 'due_on', 'amount', 'permit_critical_amount'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'amount' => 'decimal:2', 'permit_critical_amount' => 'decimal:2'];
    }
}
