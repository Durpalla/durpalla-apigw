<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use Venturecraft\Revisionable\RevisionableTrait;

class Booking extends Model
{
    use SoftDeletes, RevisionableTrait;
    protected $fillable = ['booking_date', 'customer_id', 'user_id', 'vat_amount', 'vat_total', 'charge_amount', 'charge_total', 'booking_party', 'status', 'total_amount', 'total_discount', 'payment_status', 'total_payable', 'platform', 'ticket_blacker'];

    public function bookingItems()
    {
        return $this->hasMany(BookingItem::class, 'booking_id', 'id');
    }

    public function bookingConfirmed()
    {
        return $this->hasMany(BookingItem::class, 'booking_id', 'id')->where('status', 1);
    }

    public function customer()
    {
    	return $this->belongsTo(User::class, 'customer_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function officer()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function payment()
    {
    	return $this->belongsTo(Payment::class, 'id', 'booking_id')->orderByDesc('id');
    }

    public function cancellations()
    {
        return $this->hasMany(BookingCancellation::class, 'booking_id', 'id')->whereNotIn('status', [9]);
    }

    public function cancelled()
    {
        return $this->hasMany(BookingCancellation::class, 'booking_id', 'id')->whereNotIn('status', [9,0]);
    }

    public function cancelledItems()
    {
        return $this->hasMany(BookingItem::class, 'booking_id', 'id')->where('status', 2);
    }

    public function cancelationRequests()
    {
        return $this->hasOne(BookingCancellation::class, 'booking_id', 'id')->where('status', 0);
    }

    public function collections()
    {
        return $this->hasMany(PaymentCollector::class)->orderBy('created_at', 'ASC');
    }

    public function formatDailyReport(): array
    {
        return [
            'invoice' => $this->booking->id,
            'customer_id' => $this->booking->customer_id,
            'customer_name' => $this->booking->customer->name,
            'customer_mobile' => $this->booking->customer->mobile,
            'booking_date' => $this->booking->booking_date
        ];
    }

    public function format(): array
    {
        return $this->only(['id', 'status', 'created_at']) +
            [
                'customer' => $this->customer->only('id', 'name'),
                'items' => $this->bookingItems->map(function ($item) {
                    return $item->format();
                })
            ];
    }

    public static function boot() {
        parent::boot();
        static::deleting(function($booking) {
            $booking->bookingItems()->delete();
            $booking->cancellations()->delete();
            $booking->cancelationRequests()->delete();
            $booking->payment()->delete();
        });
    }
}
