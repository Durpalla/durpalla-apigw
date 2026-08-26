<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelRoomUnit extends Model
{
    protected $table = 'hotel_room_units';

    protected $fillable = [
        'hotel_room_id',
        'room_number',
        'floor',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public function hotelRoom()
    {
        return $this->belongsTo(HotelRoom::class, 'hotel_room_id', 'id');
    }
}
