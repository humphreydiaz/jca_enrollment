<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiptPrint extends Model
{
    protected $fillable = ['payment_id', 'print_number', 'printed_by', 'printed_at'];

    protected function casts(): array
    {
        return ['printed_at' => 'datetime'];
    }
}
