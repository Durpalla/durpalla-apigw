<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Discount extends Model
{
    protected $fillable = ['user_id', 'merchant_id', 'vehicle_id', 'schedule_id', 'description', 'amount', 'type', 'applicable_to', 'is_cabin', 'is_seat', 'is_deck', 'disabled_by', 'enabled_by', 'status'];
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function disableBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disabled_by', 'id');
    }

    public function merchant(): BelongsTo
    {
    	return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    public function launch(): BelongsTo
    {
    	return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function schedule(): BelongsTo
    {
    	return $this->belongsTo(VehicleSchedule::class, 'schedule_id', 'id');
    }
}
