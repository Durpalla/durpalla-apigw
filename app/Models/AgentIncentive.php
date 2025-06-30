<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class AgentIncentive extends Model
{
    protected $fillable = ['agent_id', 'incentive', 'incentive_type'];
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id', 'agent_id');
    }
}
