<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class SaasCommissionLedger extends Model
{
    public const STATUS_ACCRUED = 'accrued';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'booking_id',
        'booking_item_id',
        'booking_hotel_item_id',
        'hotel_reservation_id',
        'merchant_id',
        'subscription_id',
        'plan_id',
        'service_type',
        'channel',
        'base_amount',
        'commission_rate',
        'commission_amount',
        'status',
        'settlement_id',
        'settled_at',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'settled_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SaasSubscription::class, 'subscription_id', 'id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'plan_id', 'id');
    }
}
