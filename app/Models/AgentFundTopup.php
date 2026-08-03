<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agent wallet top-up. Also acts as a payment-like object for gateway handlers
 * (bKash/Nagad expect transaction_id, paid_amount, gateway_initiated_id, successful/failed).
 */
class AgentFundTopup extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'method',
        'gateway_id',
        'transaction_ref',
        'gateway_trx_id',
        'status',
        'payment_url',
        'bank_reference',
        'note',
        'approved_by',
        'approved_at',
        'credited_at',
        'meta',
        // Payment-compat aliases (mapped via mutators → real columns).
        'transaction_id',
        'paid_amount',
        'gateway_initiated_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'credited_at' => 'datetime',
        'meta' => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'user_id');
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class, 'gateway_id');
    }

    public function getTransactionIdAttribute(): ?string
    {
        return $this->attributes['transaction_ref'] ?? null;
    }

    public function setTransactionIdAttribute($value): void
    {
        $this->attributes['transaction_ref'] = $value;
    }

    public function getPaidAmountAttribute(): float
    {
        return (float) ($this->attributes['amount'] ?? 0);
    }

    public function setPaidAmountAttribute($value): void
    {
        // Keep amount authoritative; ignore gateway overwrites of paid_amount.
        if (! isset($this->attributes['amount']) || (float) $this->attributes['amount'] <= 0) {
            $this->attributes['amount'] = round((float) $value, 2);
        }
    }

    public function getGatewayInitiatedIdAttribute(): ?string
    {
        return $this->attributes['gateway_trx_id'] ?? null;
    }

    public function setGatewayInitiatedIdAttribute($value): void
    {
        $this->attributes['gateway_trx_id'] = $value;
    }

    public function successful(): void
    {
        if ($this->status === 'success') {
            return;
        }
        $this->status = 'success';
        $this->save();
        app(\App\Services\AgentFundTopupService::class)->creditAfterGatewaySuccess($this);
    }

    public function failed(): void
    {
        if (in_array($this->status, ['success', 'failed'], true)) {
            return;
        }
        $this->status = 'failed';
        $this->save();
    }
}
