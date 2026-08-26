<?php

namespace App\Models\SeatLayout;

use App\Models\Vehicle;
use App\Models\BookingItem;
use Illuminate\Database\Eloquent\Model;

class Seat extends Model
{
    protected $fillable = [
        'seat_layout_id',
        'vehicle_id',
        'seat_number',
        'row_number',
        'column_number',
        'seat_type',
        'gender_rule',
        'adjacent_seats',
        'is_window',
        'is_aisle',
        'is_emergency_exit',
        'is_disabled_accessible',
        'base_price',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'row_number' => 'integer',
        'column_number' => 'integer',
        'adjacent_seats' => 'array',
        'is_window' => 'boolean',
        'is_aisle' => 'boolean',
        'is_emergency_exit' => 'boolean',
        'is_disabled_accessible' => 'boolean',
        'base_price' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function seatLayout()
    {
        return $this->belongsTo(SeatLayout::class, 'seat_layout_id', 'id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function tripInventories()
    {
        return $this->hasMany(TripSeatInventory::class, 'seat_id', 'id');
    }

    public function prices()
    {
        return $this->hasMany(SeatPrice::class, 'seat_id', 'id');
    }

    public function adjacentSeats()
    {
        if (!$this->adjacent_seats) {
            return collect([]);
        }
        return self::whereIn('id', $this->adjacent_seats)->get();
    }
}
