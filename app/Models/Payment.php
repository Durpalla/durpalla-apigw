<?php

namespace App\Models;

use App\Constants\AppConst;
use App\Services\Hotel\HotelBookingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Payment extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'transaction_id',
        'customer_id',
        'booking_id',
        'gateway_id',
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
        'gateway_initiated_id'
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
    	return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class);
    }

    public function getNiceStatusAttribute(): string
    {
        return Str::ucfirst($this->status);
    }

    public function successful(): void
    {
        $hotelRes = HotelReservation::query()
            ->where('booking_id', $this->booking_id)
            ->where('status', HotelReservation::STATUS_PENDING_PAYMENT)
            ->first();
        if ($hotelRes) {
            DB::transaction(function () use ($hotelRes) {
                $hotelBooking = app(HotelBookingService::class);
                $checkIn = Carbon::parse($hotelRes->check_in);
                $checkOut = Carbon::parse($hotelRes->check_out);
                $hotelBooking->finalizeInventoryForStoredQuote(
                    is_array($hotelRes->quote_json) ? $hotelRes->quote_json : null,
                    $checkIn,
                    $checkOut,
                    $hotelRes->roomType,
                );
                $hotelRes->update(['status' => HotelReservation::STATUS_CONFIRMED]);
                if ($this->booking) {
                    $this->booking->update(['status' => AppConst::BOOKING_COMPLETE]);
                }
            });
            $this->update(['status' => 'success']);

            return;
        }

        $this->update(['status' => 'success']);
        if ($this->booking && $this->booking->status != AppConst::BOOKING_PENDING) {
            $this->booking->update(['status' => AppConst::BOOKING_COMPLETE]);
            $this->bookingItems->each(function ($item) {
                $item->update(['status' => AppConst::BOOKING_ITEM_ACTIVE]);
            });
        }
    }

    public function failed(): void
    {
        $this->update(['status' => 'failed']);

        $booking = $this->booking;
        if (! $booking) {
            return;
        }

        $hotelRes = HotelReservation::query()
            ->where('booking_id', $booking->id)
            ->where('status', HotelReservation::STATUS_PENDING_PAYMENT)
            ->first();

        if ($hotelRes) {
            DB::transaction(function () use ($hotelRes, $booking) {
                $hotelBooking = app(HotelBookingService::class);
                $checkIn = Carbon::parse($hotelRes->check_in);
                $checkOut = Carbon::parse($hotelRes->check_out);
                $hotelBooking->releaseInventoryForStoredQuote(
                    is_array($hotelRes->quote_json) ? $hotelRes->quote_json : null,
                    $checkIn,
                    $checkOut,
                    $hotelRes->roomType,
                );
                $hotelRes->update(['status' => HotelReservation::STATUS_FAILED]);
                if ($booking->status === AppConst::BOOKING_PENDING) {
                    $booking->update(['status' => AppConst::BOOKING_FAILED]);
                }
            });

            return;
        }

        if ($booking->status != AppConst::BOOKING_PENDING) {
            $booking->update(['status' => AppConst::BOOKING_FAILED]);
            $this->bookingItems->each(function ($item) {
                $item->update(['status' => AppConst::BOOKING_ITEM_FAILED]);
            });
        }
    }

    public function format(): array
    {
        return $this->only(['id', 'booking_id', 'paid_amount', 'nice_status', 'transaction_id', 'created_at', 'currency']) +
            [
                'gateway' => $this->gateway->only(['id', 'name']),
            ];
    }
}
