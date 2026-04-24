<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotel extends Model
{
    protected $fillable = [
        'name', 'slug', 'city', 'address', 'lat', 'lng', 'star_rating',
        'aggregate_rating', 'review_count', 'description', 'policies', 'status',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'aggregate_rating' => 'decimal:2',
        ];
    }

    public function photos(): HasMany
    {
        return $this->hasMany(HotelPhoto::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(HotelReview::class)->orderByDesc('reviewed_at');
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(HotelRoomType::class);
    }
}
