<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pnr',
        'booking_date',
        'customer_id',
        'user_id', // maps to booked_by_id via mutator for mass assignment
        'booked_by_type',
        'booked_by_id',
        'vat_amount',
        'vat_total',
        'charge_amount',
        'charge_total',
        'booking_party',
        'party_id',
        'status',
        'total_amount',
        'total_discount',
        'payment_status',
        'total_payable',
        'platform',
        'ticket_blacker',
        // Multi-service support
        'service_type',
        'from_date',
        'to_date',
        'supplier_id',
        'supplier_booking_reference',
        'referring_agent_id',
        'commission_accruals_checked_at',
        // Reseller channel
        'payment_token',
        'payment_deadline',
        'platform_commission_amount',
        'reseller_commission_amount',
        'commission_share_percent',
        'wallet_debit_amount',
    ];

    protected $casts = [
        'payment_deadline' => 'datetime',
        'commission_accruals_checked_at' => 'datetime',
    ];

    /**
     * Public support-friendly booking reference (never expose primary key publicly).
     */
    public function publicReference(bool $persist = true): string
    {
        return app(\App\Services\BookingPnrService::class)->ensureFor($this, $persist);
    }

    public function party()
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

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
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    /**
     * Legacy alias: actor who created the booking (via bookedBy morph).
     */
    public function user()
    {
        return $this->bookedBy();
    }

    /**
     * Legacy alias: officer who created the booking (via bookedBy morph).
     */
    public function officer()
    {
        return $this->bookedBy();
    }

    /**
     * Polymorphic actor who created the booking (admin, merchant, staff, agent, etc.).
     */
    public function bookedBy()
    {
        return $this->morphTo(__FUNCTION__, 'booked_by_type', 'booked_by_id');
    }

    /**
     * Legacy user_id reads booked_by_id.
     */
    public function getUserIdAttribute(): ?int
    {
        $id = $this->attributes['booked_by_id'] ?? null;

        return $id !== null ? (int) $id : null;
    }

    /**
     * Legacy user_id writes booked_by_id (booked_by_type left for AuthActor when empty).
     */
    public function setUserIdAttribute($value): void
    {
        $this->attributes['booked_by_id'] = $value;
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'booking_id', 'id')->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'booking_id', 'id');
    }

    public function hotelReservation()
    {
        return $this->hasOne(HotelReservation::class, 'booking_id', 'id');
    }

    public function cancellations()
    {
        return $this->hasMany(BookingCancellation::class, 'booking_id', 'id')->whereNotIn('status', [9]);
    }

    public function cancelled()
    {
        return $this->hasMany(BookingCancellation::class, 'booking_id', 'id')->whereNotIn('status', [9, 0]);
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
            'booking_date' => $this->booking->booking_date,
        ];
    }

    public function format(): array
    {
        $pnr = $this->publicReference();

        return $this->only(['id', 'status', 'created_at']) +
            [
                'pnr' => $pnr,
                'booking_reference' => $pnr,
                'customer' => $this->customer->only('id', 'name'),
                'items' => $this->bookingItems->map(function ($item) {
                    return $item->format();
                }),
                'qr' => upload_asset('qrs/'.$this->id.'.png'),
            ];
    }

    public static function boot()
    {
        parent::boot();
        static::deleting(function ($booking) {
            $booking->bookingItems()->delete();
            $booking->cancellations()->delete();
            $booking->cancelationRequests()->delete();
            $booking->payment()->delete();
        });
    }
}
