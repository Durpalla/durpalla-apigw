<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentBalance extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'balance', 'last_withdrawal', 'last_withdrawal_date'];

    // `last_withdrawal_date` has no DB default and is NOT NULL, so a fresh
    // AgentBalance (an agent's first-ever credit/topup) fails to save unless
    // this is pre-filled - firstOrNew() only merges the lookup attributes.
    protected $attributes = [
        'balance' => 0,
        'last_withdrawal' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (AgentBalance $balance) {
            $balance->last_withdrawal_date ??= now();
        });
    }
}
