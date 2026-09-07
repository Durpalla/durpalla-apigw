<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Boat extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'merchant_id',
        'title',
        'short_description',
        'long_description',
        'capacity_max',
        'city_id',
        'base_location',
        'amenities',
        'status',
        'cancellation_policy',
    ];

    protected function casts(): array
    {
        return [
            'merchant_id' => 'integer',
            'city_id' => 'integer',
            'capacity_max' => 'integer',
            'status' => 'integer',
            'amenities' => 'array',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id', 'id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(BoatImage::class, 'boat_id', 'id')->orderBy('sort_order');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(BoatRate::class, 'boat_id', 'id');
    }

    public function tripPackages(): HasMany
    {
        return $this->hasMany(BoatTripPackage::class, 'boat_id', 'id');
    }

    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingBoatItem::class, 'boat_id', 'id');
    }

    public function holds(): HasMany
    {
        return $this->hasMany(BoatHold::class, 'boat_id', 'id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }
}
