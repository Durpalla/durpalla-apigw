<?php

namespace App\Models;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class SaasSubscription extends Model
{
    use \App\Traits\Auditable;

    public const STATUS_TRIALING = 'trialing';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'merchant_id',
        'plan_id',
        'status',
        'billing_cycle',
        'starts_at',
        'current_period_start',
        'current_period_end',
        'trial_ends_at',
        'cancelled_at',
        'monthly_fee',
        'commission_rate',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'monthly_fee' => 'decimal:2',
        'commission_rate' => 'decimal:2',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'plan_id', 'id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SaasSubscriptionInvoice::class, 'subscription_id', 'id');
    }

    /**
     * Active means the merchant can use all channels normally.
     */
    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_TRIALING], true);
    }

    /**
     * OTA-only mode: overdue accounts keep earning platform commission via OTA,
     * but merchant/counter/desk channels are cut.
     */
    public function isOtaOnly(): bool
    {
        return in_array($this->status, [self::STATUS_PAST_DUE, self::STATUS_SUSPENDED], true);
    }
}
