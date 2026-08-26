<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelRoomInventory extends Model
{
    protected $table = 'hotel_room_inventory';

    protected $fillable = [
        'hotel_id',
        'room_type_id',
        'date',
        'total_rooms',
        'booked_rooms',
        'held_rooms',
        'available_rooms',
    ];

    protected $casts = [
        'date' => 'date',
        'total_rooms' => 'integer',
        'booked_rooms' => 'integer',
        'held_rooms' => 'integer',
        'available_rooms' => 'integer',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class, 'hotel_id', 'id');
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class, 'room_type_id', 'id');
    }
}
