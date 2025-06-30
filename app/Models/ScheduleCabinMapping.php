<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Venturecraft\Revisionable\RevisionableTrait;

class ScheduleCabinMapping extends Model
{
    use RevisionableTrait;
    protected $fillable = [
        'is_reserved',
        'ownership',
        'booked',
        'ghat_id',
        'vehicle_id',
        'merchant_id',
        'schedule_id',
        'cabin_id',
        'type',
        'type_id',
        'fare',
        'is_locked',
        'honorium',
        'service_charge',
        'service_charge_type',
        'floor',
        'cabin_position',
        'cabin_row',
        'passenger_capacity',
        'is_advance',
        'booking_id'
    ];

	public function schedule(): BelongsTo
    {
		return $this->belongsTo(VehicleSchedule::class);
	}

    public function cabin(): BelongsTo
    {
    	return $this->belongsTo(Cabin::class);
    }

    public function books(): HasMany
    {
        return $this->hasMany(BookingItem::class, 'cabin_id', 'cabin_id')->latest();
    }

    public function lastBook(): HasOne
    {
        return $this->hasOne(BookingItem::class, 'cabin_id', 'cabin_id')->latest();
    }

    public function ghat(): BelongsTo
    {
        return $this->belongsTo(Ghat::class);
    }

    public function cabinType(): BelongsTo
    {
        return $this->belongsTo(CabinType::class, 'type_id', 'id');
    }

    public function resolveChildRouteBinding($childType, $value, $field)
    {
        // TODO: Implement resolveChildRouteBinding() method.
    }
}
