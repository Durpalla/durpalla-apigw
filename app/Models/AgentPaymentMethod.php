<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentPaymentMethod extends Model
{
    protected $fillable = ['type', 'user_id', 'account_no', 'account_name', 'bank_name', 'branch'];
}
