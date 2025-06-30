<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentBalance extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'balance', 'last_withdrawal', 'last_withdrawal_date'];
}
