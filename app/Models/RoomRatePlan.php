<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomRatePlan extends Model
{
    protected $fillable = [
        'room_type_id',
        'name',
        'meal_plan',
        'cancellation_policy',
        'external_key',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public function roomType()
    {
        return $this->belongsTo(RoomType::class, 'room_type_id', 'id');
    }

    public function bookingItems()
    {
        return $this->hasMany(BookingHotelItem::class, 'rate_plan_id', 'id');
    }

    public function childPolicies()
    {
        return $this->hasMany(HotelChildPolicy::class, 'rate_plan_id', 'id');
    }
}
