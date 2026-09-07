<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourHold extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONSUMED = 'consumed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tour_id',
        'departure_id',
        'user_id',
        'merchant_id',
        'agent_id',
        'places',
        'unit_price',
        'total_price',
        'status',
        'expires_at',
        'guest_json',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'tour_id' => 'integer',
            'departure_id' => 'integer',
            'user_id' => 'integer',
            'merchant_id' => 'integer',
            'agent_id' => 'integer',
            'places' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'expires_at' => 'datetime',
            'guest_json' => 'array',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class, 'tour_id', 'id');
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(TourDeparture::class, 'departure_id', 'id');
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
