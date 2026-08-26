<?php

namespace App\Models\SeatLayout;

use App\Models\VehicleSchedule;
use App\Models\Cabin;
use App\Models\BookingItem;
use Illuminate\Database\Eloquent\Model;

class TripCabinInventory extends Model
{
    protected $table = 'trip_cabin_inventory';

    protected $fillable = [
        'schedule_id',
        'cabin_id',
        'status',
        'booking_item_id',
        'locked_by',
        'locked_until',
    ];

    protected $casts = [
        'locked_until' => 'datetime',
    ];

    public function schedule()
    {
        return $this->belongsTo(VehicleSchedule::class, 'schedule_id', 'id');
    }

    public function cabin()
    {
        return $this->belongsTo(Cabin::class, 'cabin_id', 'id');
    }

    public function bookingItem()
    {
        return $this->belongsTo(BookingItem::class, 'booking_item_id', 'id');
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked' && 
               $this->locked_until && 
               $this->locked_until->isFuture();
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available' && !$this->isLocked();
    }
}
