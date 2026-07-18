<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class BookingCancellation extends Model
{
    protected $fillable = ['booking_id', 'customer_id', 'user_id', 'items', 'vat_refundable', 'charge_refundable', 'total_refundable', 'refund_amount', 'status'];

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
