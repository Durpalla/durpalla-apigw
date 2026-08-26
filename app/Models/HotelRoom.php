<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class HotelRoom extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'hotel_id',
        'room_type_id',
        'name',
        'max_adults',
        'max_children',
        'max_occupancy',
        'base_price',
        'peak_price',
        'off_peak_price',
        'total_rooms',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'max_adults' => 'integer',
        'max_children' => 'integer',
        'max_occupancy' => 'integer',
        'base_price' => 'decimal:2',
        'peak_price' => 'decimal:2',
        'off_peak_price' => 'decimal:2',
        'total_rooms' => 'integer',
        'status' => 'integer',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class, 'hotel_id', 'id');
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class, 'room_type_id', 'id');
    }

    public function bookingItems()
    {
        return $this->hasMany(BookingHotelItem::class, 'room_id', 'id');
    }

    public function inventory()
    {
        return $this->hasMany(HotelRoomInventory::class, 'room_type_id', 'room_type_id')
            ->where('hotel_id', $this->hotel_id);
    }

    public function roomUnits()
    {
        return $this->hasMany(HotelRoomUnit::class, 'hotel_room_id', 'id');
    }

    public function facilities()
    {
        return $this->belongsToMany(
            HotelFacility::class,
            'hotel_room_facility',
            'hotel_room_id',
            'hotel_facility_id'
        )->withTimestamps();
    }

    public function images()
    {
        return $this->hasMany(HotelRoomImage::class, 'hotel_room_id', 'id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
