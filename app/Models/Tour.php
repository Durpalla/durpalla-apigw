<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tour extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'merchant_id',
        'title',
        'destination',
        'city_id',
        'duration_days',
        'duration_nights',
        'status',
        'short_description',
        'long_description',
        'meeting_point',
        'min_people',
        'max_people',
        'cancellation_policy',
    ];

    protected function casts(): array
    {
        return [
            'merchant_id' => 'integer',
            'city_id' => 'integer',
            'duration_days' => 'integer',
            'duration_nights' => 'integer',
            'status' => 'integer',
            'min_people' => 'integer',
            'max_people' => 'integer',
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

    public function departures(): HasMany
    {
        return $this->hasMany(TourDeparture::class, 'tour_id', 'id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(TourImage::class, 'tour_id', 'id');
    }

    public function itineraryDays(): HasMany
    {
        return $this->hasMany(TourItineraryDay::class, 'tour_id', 'id')->orderBy('day_number');
    }

    public function holds(): HasMany
    {
        return $this->hasMany(TourHold::class, 'tour_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
