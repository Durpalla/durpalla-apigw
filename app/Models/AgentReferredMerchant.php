<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentReferredMerchant extends Model
{
    public const STATUS_LEAD = 'lead';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_LIVE = 'live';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'agent_id',
        'merchant_id',
        'name',
        'business_type',
        'contact_person',
        'contact_mobile',
        'city',
        'address',
        'trade_license_no',
        'notes',
        'status',
        'reject_reason',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AgentReferredMerchantDocument::class, 'referred_merchant_id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_LEAD, self::STATUS_SUBMITTED, self::STATUS_REJECTED], true);
    }
}
