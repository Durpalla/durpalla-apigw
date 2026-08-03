<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class BookingCancellation extends Model
{
    protected $fillable = [
        'booking_id',
        'customer_id',
        'user_id',
        'items',
        'type',
        'service_type',
        'vat_refundable',
        'charge_refundable',
        'total_refundable',
        'refund_percent_applied',
        'policy_snapshot',
        'refund_amount',
        'refund_error',
        'status',
        'transaction_id',
        'payment_method',
    ];

    protected function casts(): array
    {
        return [
            'policy_snapshot' => 'array',
            'total_refundable' => 'float',
            'refund_amount' => 'float',
            'refund_percent_applied' => 'float',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'booking_id', 'booking_id');
    }

    public function customer()
    {
    	return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function officer()
    {
    	return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function cancellationItems()
    {
        return $this->hasMany(BookingCancellationItem::class);
    }

    public function paymentRefund()
    {
        return $this->hasOne(PaymentRefund::class, 'booking_cancellation_id');
    }

    public function bookingItems()
    {
    	return $this->hasMany(BookingItem::class, 'booking_id', 'booking_id');
    }

    public static function boot() {
        parent::boot();
        static::deleting(function($vehicle) {

        });
    }
}
