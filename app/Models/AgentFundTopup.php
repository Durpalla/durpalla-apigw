<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
