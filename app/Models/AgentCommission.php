<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class AgentCommission extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'user_id',
        'booking_item_id',
        'commission_date',
        'purpose',
        'type',
        'total_sale',
        'amount',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function bookingItem(): BelongsTo
    {
        return $this->belongsTo(BookingItem::class, 'booking_item_id', 'id');
    }
}
