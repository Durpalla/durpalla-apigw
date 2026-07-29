<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentWithdrawal extends Model
{
    protected $fillable = [
        'status',
        'transaction_reference',
        'agent_payment_method_id',
        'user_id',
        'officer_id',
        'approved_by',
        'approved_at',
        'processed_by',
        'processed_at',
        'decline_reason',
        'balance',
        'amount',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function agentPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(AgentPaymentMethod::class);
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id', 'id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by', 'id');
    }
}
