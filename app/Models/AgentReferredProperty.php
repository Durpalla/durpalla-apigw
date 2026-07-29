<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentReferredProperty extends Model
{
    protected $fillable = [
        'agent_id',
        'merchant_id',
        'hotel_id',
        'vehicle_id',
        'name',
        'type',
        'contact_person',
        'contact_mobile',
        'city',
        'address',
        'trade_license_no',
        'notes',
        'active',
        'status',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }
}
