<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AgentCommissionAccrual extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_VOID = 'void';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'agent_id', 'booking_id', 'booking_item_id', 'source_type', 'source_id',
        'source_key', 'kind', 'service_type', 'base_amount', 'rate',
        'incentive_type', 'amount', 'eligible_at', 'status', 'commission_id',
        'reversal_commission_id', 'settled_at', 'voided_at', 'reversed_at', 'meta',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'rate' => 'decimal:4',
        'amount' => 'decimal:2',
        'eligible_at' => 'datetime',
        'settled_at' => 'datetime',
        'voided_at' => 'datetime',
        'reversed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingItem()
    {
        return $this->belongsTo(BookingItem::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
