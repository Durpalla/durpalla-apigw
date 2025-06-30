<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class PaymentCollector extends Model
{
    public $fillable = ['booking_id', 'payment_id', 'supervisor_id', 'amount', 'remarks', 'payment_type'];

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id', 'id');
    }
}
