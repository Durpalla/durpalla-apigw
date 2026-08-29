<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

class MerchantSettlement extends Model
{
    protected $fillable = [
        'merchant_id',
        'period_from',
        'period_to',
        'settlement_scope',
        'trip_id',
        'total_sale_amount',
        'platform_charge',
        'merchant_amount',
        'gross_merchant_payable',
        'commission_receivable',
        'status',
        'paid_at',
        'payment_reference',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'total_sale_amount' => 'decimal:2',
        'platform_charge' => 'decimal:2',
        'merchant_amount' => 'decimal:2',
        'gross_merchant_payable' => 'decimal:2',
        'commission_receivable' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const SCOPE_PERIOD = 'period';
    public const SCOPE_TRIP = 'trip';

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MerchantSettlementItem::class, 'merchant_settlement_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function scopeFilter(Builder $query, Request $request): Builder
    {
        if ($request->filled('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('period_from')) {
            $query->where('period_to', '>=', $request->period_from);
        }
        if ($request->filled('period_to')) {
            $query->where('period_from', '<=', $request->period_to);
        }

        return $query;
    }
}
