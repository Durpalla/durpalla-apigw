<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Venturecraft\Revisionable\RevisionableTrait;

class Cabin extends Model
{
    use RevisionableTrait;
    protected $fillable = [
        'vehicle_id',
        'marchant_id',
        'ownership',
        'ghat_id',
        'cabin_no',
        'type_id',
        'fare',
        'child_fare',
        'infant_fare',
        'cabin_row',
        'floor',
        'cabin_position',
        'passenger_capacity',
        'is_reserved',
        'type',
        'service_charge',
        'service_charge_type',
        'vehicle_type'
    ];

    public function launch() : belongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function vehicle() : belongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function cabinType() : belongsTo
    {
    	return $this->belongsTo(CabinType::class, 'type_id', 'id');
    }

    public function lastBooked() : hasOne
    {
        return $this->hasOne(BookingItem::class, 'cabin_id', 'id')->latest();
    }

    public function books() : hasMany
    {
        return $this->hasMany(BookingItem::class, 'cabin_id', 'id')->latest();
    }

    public function bookings() : hasMany
    {
    	return $this->hasMany(BookingItem::class, 'cabin_id', 'id');
    }

    public function mapping() : hasOne
    {
        return $this->hasOne(ScheduleCabinMapping::class, 'cabin_id', 'id');
    }

    public function locks() : hasMany
    {
        return $this->hasMany(CabinLock::class, 'cabin_id', 'id')->latest();
    }

    public function ghat(): BelongsTo
    {
        return $this->belongsTo(Ghat::class);
    }

    public static function boot() {
        parent::boot();
        static::creating(function($model) {
            $model->created_by = auth()->id();
        });
    }
}
