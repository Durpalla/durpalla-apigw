<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleRouteMapping extends Model
{
	protected $fillable = array('vehicle_id', 'merchant_id', 'route_id', 'assigned_by');
    public function route(): BelongsTo
    {
    	return $this->belongsTo(VehicleRoute::class, 'route_id', 'id');
    }
}
