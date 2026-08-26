<?php

namespace App\Models\SeatLayout;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;

class SeatLayout extends Model
{
    protected $fillable = [
        'name',
        'code',
        'vehicle_type',
        'total_rows',
        'seats_per_row',
        'total_seats',
        'layout_config',
        'description',
        'is_active',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'seats_per_row' => 'integer',
        'total_seats' => 'integer',
        'layout_config' => 'array',
        'is_active' => 'boolean',
    ];

    public function seats()
    {
        return $this->hasMany(Seat::class, 'seat_layout_id', 'id');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'seat_layout_id', 'id');
    }
}
