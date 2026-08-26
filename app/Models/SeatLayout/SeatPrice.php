<?php

namespace App\Models\SeatLayout;

use App\Models\Vehicle;
use App\Models\VehicleRoute;
use App\Models\VehicleSchedule;
use Illuminate\Database\Eloquent\Model;

class SeatPrice extends Model
{
    protected $fillable = [
        'vehicle_id',
        'route_id',
        'schedule_id',
        'seat_id',
        'seat_type',
        'price_type',
        'adult_price',
        'child_price',
        'infant_price',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected $casts = [
        'adult_price' => 'decimal:2',
        'child_price' => 'decimal:2',
        'infant_price' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function route()
    {
        return $this->belongsTo(VehicleRoute::class, 'route_id', 'id');
    }

    public function schedule()
    {
        return $this->belongsTo(VehicleSchedule::class, 'schedule_id', 'id');
    }

    public function seat()
    {
        return $this->belongsTo(Seat::class, 'seat_id', 'id');
    }
}
