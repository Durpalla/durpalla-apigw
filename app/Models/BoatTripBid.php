<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoatTripBid extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'request_id',
        'merchant_id',
        'boat_id',
        'amount',
        'message',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'request_id' => 'integer',
            'merchant_id' => 'integer',
            'boat_id' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(BoatTripRequest::class, 'request_id', 'id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    public function boat(): BelongsTo
    {
        return $this->belongsTo(Boat::class, 'boat_id', 'id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
