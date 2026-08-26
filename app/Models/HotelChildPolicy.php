<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelChildPolicy extends Model
{
    protected $fillable = [
        'hotel_id',
        'rate_plan_id',
        'min_age',
        'max_age',
        'bed_type',
        'price_type',
        'price_value',
    ];

    protected $casts = [
        'min_age' => 'integer',
        'max_age' => 'integer',
        'price_value' => 'decimal:2',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class, 'hotel_id', 'id');
    }

    public function ratePlan()
    {
        return $this->belongsTo(RoomRatePlan::class, 'rate_plan_id', 'id');
    }
}
