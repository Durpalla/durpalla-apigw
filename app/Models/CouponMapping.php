<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponMapping extends Model
{
    public function merchant(): BelongsTo
    {
    	return $this->belongsTo(Merchant::class, 'item_id', 'user_id');
    }

    public function launch(): BelongsTo
    {
    	return $this->belongsTo(Vehicle::class, 'item_id', 'id');
    }

    public function route(): BelongsTo
    {
    	return $this->belongsTo(VehicleRoute::class, 'item_id', 'id');
    }

    public function customer(): BelongsTo
    {
    	return $this->belongsTo(User::class, 'item_id', 'id');
    }
}
