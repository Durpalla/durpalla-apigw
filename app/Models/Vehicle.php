<?php

namespace App\Models;

use App\Constants\AppConst;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use Venturecraft\Revisionable\RevisionableTrait;

class Vehicle extends Model
{
    use SoftDeletes, RevisionableTrait;
    protected $fillable = [
        'user_id',
        'merchant_id',
        'route_id',
        'name',
        'photo',
        'vehicle_no',
        'engine_no',
        'registration_no',
        'registration_expiry_date',
        'fitness_expiry_date',
        'passengers_capacity',
        'vehicle_type',
        'disabled_by',
        'enabled_by',
        'nid_verification_check',
        'number_of_floor',
        'ac_available',
        'default_tab',
        'default_floor',
        'status'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(VehicleSchedule::class, 'vehicle_id', 'id');
    }

    public function activeTrips(): HasMany
    {
        return $this->hasMany(VehicleSchedule::class, 'vehicle_id', 'id')->where('schedule_date', '>=', date('Y-m-d'))->where('status', AppConst::SCHEDULE_ACTIVE)->orderBy('leaving_at', 'ASC');
    }

    public function trip()
    {
        return $this->trips()->where('schedule_date', '>=', date('Y-m-d'))->orderBy('leaving_at', 'ASC')->first();
    }

    public function enableBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enabled_by', 'id');
    }

    public function disableBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disabled_by', 'id');
    }

    public function merchant()
    {
    	return $this->belongsTo(Merchant::class, 'merchant_id', 'user_id')->withTrashed();
    }

    public function supervisors(): HasMany
    {
        return $this->hasMany(VehicleSupervisor::class, 'vehicle_id', 'id');
    }

    public function routeMappings(): HasMany
    {
        return $this->hasMany(VehicleRouteMapping::class, 'vehicle_id', 'id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(VehicleRoute::class, 'route_id', 'id');
    }

    public function routeMap(): HasOne
    {
        return $this->hasOne(VehicleRouteMapping::class, 'vehicle_id', 'id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(VehicleSchedule::class, 'vehicle_id', 'id');
    }

    public function cabins(): HasMany
    {
        return $this->hasMany(Cabin::class, 'vehicle_id', 'id')->where('type', 'cabin');
    }

    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class, 'vehicle_id', 'id');
    }

    public function activeBookings(): HasMany
    {
        return $this->bookingItems()->where('status', 1);
    }

    public function cabinBookings(): HasMany
    {
        return $this->bookingItems()->where('booking_type', 'cabin');
    }

    public function seatBookings(): HasMany
    {
        return $this->bookingItems()->where('booking_type', 'seat');
    }

    public function deckBookings(): HasMany
    {
        return $this->bookingItems()->where('booking_type', 'deck');
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Cabin::class, 'vehicle_id', 'id')->where('type', 'seat');
    }

    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'partner_vehicle',
            'vehicle_id', 'partner_id');
    }

    public static function boot() {
        parent::boot();
        static::created(function ($model) {
            $model->user_id = auth()->id();
        });

        static::deleting(function($vehicle) {
            $vehicle->supervisors()->delete();
            $vehicle->routeMappings()->delete();
            $vehicle->schedules()->each(function($item, $key) {
                $item->delete();
            });
            $vehicle->cabins()->delete();
            $vehicle->seats()->delete();
        });

        static::restoring(function($vehicle) {
            $vehicle->merchant()->restore();
            $vehicle->supervisors()->each(function($item, $k) {
                $item->restore();
            });
            $vehicle->routeMappings()->each(function($item, $key){
                $item->restore();
            });
            $vehicle->cabins()->each(function($item, $key){
                $item->restore();
            });
            $vehicle->seats()->each(function($item, $key){
                $item->restore();
            });
        });
    }
}
