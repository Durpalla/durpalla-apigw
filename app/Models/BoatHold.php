<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoatHold extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONSUMED = 'consumed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'boat_id',
        'user_id',
        'merchant_id',
        'agent_id',
        'rental_mode',
        'pricing_source',
        'starts_at',
        'ends_at',
        'package_id',
        'trip_request_id',
        'bid_id',
        'guests',
        'unit_price',
        'total_price',
        'units',
        'stoppages_json',
        'status',
        'expires_at',
        'guest_json',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'boat_id' => 'integer',
            'user_id' => 'integer',
            'merchant_id' => 'integer',
            'agent_id' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'package_id' => 'integer',
            'trip_request_id' => 'integer',
            'bid_id' => 'integer',
            'guests' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'units' => 'integer',
            'stoppages_json' => 'array',
            'expires_at' => 'datetime',
            'guest_json' => 'array',
        ];
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

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'user_id', 'id');
    }
}
