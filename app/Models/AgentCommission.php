<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCommission extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'booking_item_id',
        'commission_date',
        'purpose',
        'type',
        'total_sale',
        'amount',
    ];

    public function user(): BelongsTo
    {
        // Legacy alias — user_id stores agents.id after agent table split.
        return $this->belongsTo(Agent::class, 'user_id', 'id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'user_id', 'id');
    }

    public function bookingItem(): BelongsTo
    {
        return $this->belongsTo(BookingItem::class, 'booking_item_id', 'id');
    }

    /**
     * Booking commissions the agent earns (excludes withdrawals / fund debits).
     */
    public function scopeBookingEarnings($query)
    {
        return $query
            ->where('type', 'credit')
            ->where('purpose', 'commission');
    }
}
