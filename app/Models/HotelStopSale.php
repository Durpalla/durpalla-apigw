<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelStopSale extends Model
{
    protected $fillable = [
        'hotel_id',
        'room_type_id',
        'starts_on',
        'ends_on',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'hotel_id' => 'integer',
        'room_type_id' => 'integer',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'created_by' => 'integer',
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
