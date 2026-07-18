<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\User;


class BookingItem extends Model
{

    protected $fillable = [
        'booking_id',
        'vehicle_id',
        'customer_id',
        'booking_type',
        'cabin_id',
        'price',
        'trip_id',
        'trip_date',
        'booking_date',
        'discount',
        'boarding_point',
        'passenger',
        'vat_amount',
        'charge_amount',
        'charge_type',
        'vat_applicable_to',
        'discount_type',
        'printed',
        'status',
        'honorium_charge',
        'honorium_type',
        'is_honorium',
        'booking_party',
        'incentive',
        'incentive_type',
        'route_name',
        'deck_fare_id',
        'mapping_id',
        'is_locked',
        'party_id',
        'item_type',
    ];

    protected $casts = [
        'passenger' => 'array',
        'booking_date' => 'datetime:Y-m-d H:i:s',
        'trip_date' => 'datetime:Y-m-d H:i:s',
    ];

    public function item(): BelongsTo
    {
    	return $this->belongsTo(Cabin::class, 'cabin_id', 'id');
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(ScheduleCabinMapping::class);
    }

    public function deck(): BelongsTo
    {
        return $this->belongsTo(DeckFare::class);
    }

    public function cancellation()
    {
        return $this->hasOne(BookingCancellationItem::class)->whereIn('status', [0,1, 2, 3])->first();
    }

    public function cancellations(): HasMany
    {
        return $this->hasMany(BookingCancellation::class, 'booking_id', 'booking_id')->whereNotIn('status', [0,9]);
    }

    public function cancelled()
    {
        return $this->hasOne(BookingCancellationItem::class)->whereIn('status', [1,2,3])->first();
    }

    public function refunded(): HasOne
    {
        return $this->hasOne(BookingCancellationItem::class)->where('status', 3);
    }

    public function launch(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function trip(): BelongsTo
    {
    	return $this->belongsTo(VehicleSchedule::class, 'trip_id', 'id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(ScheduleCabinMapping::class, 'schedule_id', 'trip_id');
    }

    public function booking(): BelongsTo
    {
    	return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'booking_id', 'booking_id')->latest();
    }

    public function collectors(): HasMany
    {
        return $this->hasMany(PaymentCollector::class, 'booking_id', 'booking_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function format(): array
    {
        return $this->only(['id', 'booking_type', 'price', 'trip_date', 'booking_date', 'passenger', 'route_name']) +
            [
                'vehicle' => $this->vehicle?->only(['id', 'name', 'attachment']),
                'trip' => $this->trip?->toArray(),
                'cabin' => $this->item?->only(['id', 'cabin_no', 'fare', 'passenger_capacity', 'type', 'ownership']),
            ];
    }

    public static function boot() {
        parent::boot();
        static::deleting(function($vehicle) {

        });
    }
}
