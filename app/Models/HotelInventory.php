<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelInventory extends Model
{
    protected $table = 'hotel_inventory';

    protected $fillable = [
        'hotel_room_type_id', 'night_date', 'units_total', 'units_sold', 'units_held',
    ];

    protected function casts(): array
    {
        return [
            'night_date' => 'date',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(HotelRoomType::class, 'hotel_room_type_id');
    }
}
