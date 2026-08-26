<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasPlan extends Model
{
    use \App\Traits\Auditable;

    protected $fillable = [
        'code',
        'name',
        'monthly_fee',
        'commission_rate',
        'currency',
        'max_hotel_properties',
        'max_hotel_rooms',
        'overdue_block_limit',
        'is_custom',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'monthly_fee' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'max_hotel_properties' => 'integer',
        'max_hotel_rooms' => 'integer',
        'overdue_block_limit' => 'integer',
        'is_custom' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(SaasSubscription::class, 'plan_id', 'id');
    }

    public function hasUnlimitedProperties(): bool
    {
        return $this->max_hotel_properties === null;
    }

    public function hasUnlimitedRooms(): bool
    {
        return $this->max_hotel_rooms === null;
    }
}
