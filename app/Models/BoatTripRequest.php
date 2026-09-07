<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoatTripRequest extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_AWARDED = 'awarded';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'agent_id',
        'city_id',
        'stoppage_ids',
        'starts_at',
        'ends_at',
        'guests',
        'notes',
        'status',
        'expires_at',
        'accepted_bid_id',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'agent_id' => 'integer',
            'city_id' => 'integer',
            'stoppage_ids' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'guests' => 'integer',
            'expires_at' => 'datetime',
            'accepted_bid_id' => 'integer',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id', 'id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(BoatTripBid::class, 'request_id', 'id');
    }

    public function acceptedBid(): BelongsTo
    {
        return $this->belongsTo(BoatTripBid::class, 'accepted_bid_id', 'id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}
