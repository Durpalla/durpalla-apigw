<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    protected $fillable = ['type', 'booking_id', 'payment_id', 'transaction_id', 'bank_transaction_id', 'payment_method', 'data'];
    protected $casts = [
        'data' => 'array'
    ];
}
