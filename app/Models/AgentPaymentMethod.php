<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentPaymentMethod extends Model
{
    use SoftDeletes;

    protected $fillable = ['type', 'user_id', 'account_no', 'account_name', 'bank_name', 'branch'];
}
