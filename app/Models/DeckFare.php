<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class DeckFare extends Model
{

    protected $fillable = ['route_id', 'service_charge', 'service_charge_type'];

    public function route(): BelongsTo
    {
    	return $this->belongsTo(VehicleRoute::class, 'route_id', 'id');
    }

    public function launch(): BelongsTo
    {
    	return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function departureFrom(): BelongsTo
    {
    	return $this->belongsTo(RouteProperty::class, 'departure_from', 'id');
    }

    public function departureTo(): BelongsTo
    {
    	return $this->belongsTo(RouteProperty::class, 'departure_to', 'id');
    }
}
