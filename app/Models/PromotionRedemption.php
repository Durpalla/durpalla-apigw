<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionRedemption extends Model
{
    protected $fillable = [
        'promotion_id', 'user_id', 'booking_id', 'discount_amount', 'currency',
        'status', 'applied_at', 'reversed_at',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'reversed_at' => 'datetime',
        'discount_amount' => 'float',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
