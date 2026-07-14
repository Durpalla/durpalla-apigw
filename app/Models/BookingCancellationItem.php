<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class BookingCancellationItem extends Model
{
    protected $fillable = ['officer_id', 'customer_id', 'booking_id', 'booking_item_id', 'booking_cancellation_id', 'status', 'refundable_amount'];

    public function cancellationInfo()
    {
        return $this->belongsTo(BookingCancellation::class);
    }

    public function bookingItem()
    {
        return $this->belongsTo(BookingItem::class);
    }

    public function officer()
    {
        return $this->belongsTo(User::class, 'officer_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }
}
