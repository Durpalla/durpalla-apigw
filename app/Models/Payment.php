<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class Payment extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'transaction_id',
        'customer_id',
        'booking_id',
        'paid_amount',
        'dues',
        'payment_method',
        'payment_gateway',
        'account_no',
        'store_amount',
        'currency',
        'bank_tran_id',
        'status',
        'gateway_session',
        'gateway_initiated_id',
        'gateway'
    ];

    public function booking(): BelongsTo
    {
    	return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function bookingItems() {
        return $this->hasManyThrough(BookingItem::class, Booking::class, 'id', 'booking_id');
    }

    public function customer(): BelongsTo
    {
    	return $this->belongsTo(User::class, 'customer_id', 'id');
    }
}
