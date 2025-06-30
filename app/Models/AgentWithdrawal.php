<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class AgentWithdrawal extends Model
{
    protected $fillable = ['status', 'transaction_reference', 'agent_payment_method_id', 'user_id', 'officer_id', 'balance', 'amount'];
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
}
