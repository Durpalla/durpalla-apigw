<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingBoatItem extends Model
{
    protected $fillable = [
        'booking_id',
        'boat_id',
        'rental_mode',
        'pricing_source',
        'starts_at',
        'ends_at',
        'package_id',
        'trip_request_id',
        'bid_id',
        'guests',
        'units',
        'unit_price',
        'total_price',
        'stoppages_json',
        'boat_title',
        'travelers',
    ];

    protected function casts(): array
    {
        return [
            'booking_id' => 'integer',
            'boat_id' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'package_id' => 'integer',
            'trip_request_id' => 'integer',
            'bid_id' => 'integer',
            'guests' => 'integer',
            'units' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'stoppages_json' => 'array',
            'travelers' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function boat(): BelongsTo
    {
        return $this->belongsTo(Boat::class, 'boat_id', 'id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(BoatTripPackage::class, 'package_id', 'id');
    }

    public function tripRequest(): BelongsTo
    {
        return $this->belongsTo(BoatTripRequest::class, 'trip_request_id', 'id');
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(BoatTripBid::class, 'bid_id', 'id');
    }
}
