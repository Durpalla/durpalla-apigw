<?php

namespace App\Models;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Model;

class BookingHotelItem extends Model
{
    protected $fillable = [
        'booking_id',
        'hotel_id',
        'room_id',
        'room_type_id',
        'rate_plan_id',
        'check_in_date',
        'check_out_date',
        'nights',
        'adults',
        'children',
        'children_ages',
        'guests',
        'unit_price',
        'child_price',
        'total_price',
        'external_booking_id',
        'external_reference',
        'supplier',
        'supplier_payload',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'nights' => 'integer',
        'adults' => 'integer',
        'children' => 'integer',
        'children_ages' => 'array',
        'guests' => 'array',
        'unit_price' => 'decimal:2',
        'child_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'supplier_payload' => 'array',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class, 'hotel_id', 'id');
    }

    public function room()
    {
        return $this->belongsTo(HotelRoom::class, 'room_id', 'id');
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class, 'room_type_id', 'id');
    }

    public function ratePlan()
    {
        return $this->belongsTo(RoomRatePlan::class, 'rate_plan_id', 'id');
    }
}
