<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelRoomType extends Model
{
    protected $fillable = [
        'hotel_id', 'code', 'title', 'max_occupancy', 'bed_type', 'amenities',
        'base_price_per_night', 'currency', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'base_price_per_night' => 'decimal:2',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(HotelRoomTypePhoto::class)->orderBy('sort_order');
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(HotelInventory::class);
    }
}
