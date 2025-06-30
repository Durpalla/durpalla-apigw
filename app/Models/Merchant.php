<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use Venturecraft\Revisionable\RevisionableTrait;

class Merchant extends Model
{
    use SoftDeletes, RevisionableTrait;
    protected $fillable = ['vat_visibility'];
    public function user()
    {
    	return $this->belongsTo(User::class);
    }

    /**
     * Get all of the merchant's offices.
     */
    public function offices(): MorphMany
    {
        return $this->morphMany(Office::class, 'official');
    }

    public function vehicles()
    {
    	return $this->hasMany(Vehicle::class, 'merchant_id', 'user_id');
    }

    public function cabins()
    {
        return $this->hasMany(Cabin::class, 'marchant_id', 'id')->where('type', 'cabin');
    }

    public function seats()
    {
        return $this->hasMany(Cabin::class, 'marchant_id', 'id')->where('type', 'seat');
    }

    public function schedules()
    {
        return $this->hasMany(VehicleSchedule::class, 'merchant_id', 'user_id');
    }

    public function upcomingSchedules()
    {
        return $this->schedules()->where('schedule_date', '>=', date('Y-m-d'))->orderBy('schedule_date', 'asc');
    }

    public function bookingItems()
    {
        return $this->hasManyThrough('BookingItem', 'Vehicle', 'merchant_id', 'vehicle_id')->where('status', 1);
    }

    public static function boot() {
        parent::boot();
        static::deleting(function($merchant) {
            $merchant->user()->delete();
            $merchant->vehicles()->each(function($item, $key) {
                $item->delete();
            });
        });

        static::restoring(function($merchant) {
            $merchant->user()->restore();
            $merchant->vehicles()->each(function($item, $k) {
                $item->restore();
            });
        });
    }
}
