<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentRefund extends Model
{
    protected $fillable = [
        'booking_cancellation_id',
        'payment_id',
        'amount',
        'gateway',
        'gateway_refund_id',
        'status',
        'response_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'response_payload' => 'array',
        ];
    }

    public function cancellation()
    {
        return $this->belongsTo(BookingCancellation::class, 'booking_cancellation_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
