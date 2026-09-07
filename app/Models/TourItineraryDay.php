<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourItineraryDay extends Model
{
    protected $fillable = [
        'tour_id',
        'day_number',
        'title',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'tour_id' => 'integer',
            'day_number' => 'integer',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class, 'tour_id', 'id');
    }
}
