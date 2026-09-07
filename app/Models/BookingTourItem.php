<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingTourItem extends Model
{
    protected $fillable = [
        'booking_id',
        'tour_id',
        'departure_id',
        'places',
        'unit_price',
        'total_price',
        'travelers',
        'tour_title',
        'depart_date',
        'destination',
    ];

    protected function casts(): array
    {
        return [
            'booking_id' => 'integer',
            'tour_id' => 'integer',
            'departure_id' => 'integer',
            'places' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'travelers' => 'array',
            'depart_date' => 'date',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class, 'tour_id', 'id');
    }

    public function departure(): BelongsTo
    {
        return $this->belongsTo(TourDeparture::class, 'departure_id', 'id');
    }
}
