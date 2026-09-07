<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourDeparture extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'tour_id',
        'depart_date',
        'return_date',
        'places_total',
        'places_sold',
        'places_held',
        'unit_price',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tour_id' => 'integer',
            'depart_date' => 'date',
            'return_date' => 'date',
            'places_total' => 'integer',
            'places_sold' => 'integer',
            'places_held' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class, 'tour_id', 'id');
    }

    public function holds(): HasMany
    {
        return $this->hasMany(TourHold::class, 'departure_id', 'id');
    }

    public function availablePlaces(): int
    {
        return max(0, (int) $this->places_total - (int) $this->places_sold - (int) $this->places_held);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}
