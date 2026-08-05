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
        'accrual_id',
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

    public function accrual(): BelongsTo
    {
        return $this->belongsTo(AgentCommissionAccrual::class, 'accrual_id');
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

    public function scopeCommissionLedger($query)
    {
        return $query->whereIn('purpose', ['commission', 'cancellation']);
    }

    public static function netSettledForAgent(int $agentId, ?string $date = null): float
    {
        $query = static::query()->where('user_id', $agentId)->commissionLedger();
        if ($date) {
            $query->whereDate('commission_date', $date);
        }
        return (float) $query->selectRaw(
            "COALESCE(SUM(CASE WHEN type = 'debit' THEN -amount ELSE amount END), 0) AS net"
        )->value('net');
    }
}
