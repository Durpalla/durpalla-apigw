<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;


class VehicleRoute extends Model
{

    protected $fillable = ['service_type', 'route_name', 'route_no', 'route_type'];

    public function vehicles(): HasManyThrough
    {
    	return $this->hasManyThrough(Vehicle::class, VehicleRouteMapping::class, 'vehicle_id', 'id');
    }

    public function stoppages(): BelongsToMany
    {
        return $this->belongsToMany(Ghat::class, RouteProperty::class, 'ghat_id', 'ghat_id');
    }

    public function boardingPoints(): HasMany
    {
    	return $this->hasMany(RouteProperty::class, 'route_id', 'id');
    }

    public function boardingVias(): HasMany
    {
    	return $this->hasMany(RouteProperty::class, 'route_id', 'id')->where('type', 'via');
    }

    public function startingPoint(): HasOne
    {
    	return $this->hasOne(RouteProperty::class, 'route_id', 'id')->where('type', 'start');
    }

    public function endingPoint(): HasOne
    {
    	return $this->hasOne(RouteProperty::class, 'route_id', 'id')->where('type', 'end');
    }

    public function deckFares(): HasMany
    {
        return $this->hasMany(DeckFare::class, 'route_id', 'id');
    }

    public static function boot() {
        parent::boot();
        static::deleting(function($vehicleRoute) {
            $vehicleRoute->deckFares()->each(function($item, $key) {
                $item->delete();
            });
            $vehicleRoute->startingPoint()->delete();
            $vehicleRoute->endingPoint()->delete();
            $vehicleRoute->boardingVias()->delete();
        });
    }
}
