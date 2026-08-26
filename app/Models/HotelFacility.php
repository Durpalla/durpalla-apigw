<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelFacility extends Model
{
    protected $fillable = [
        'name',
        'code',
        'icon',
        'category',
    ];

    public function hotels()
    {
        return $this->belongsToMany(Hotel::class, 'hotel_facility_hotel', 'hotel_facility_id', 'hotel_id');
    }
}
