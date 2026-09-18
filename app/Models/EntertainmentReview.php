<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntertainmentReview extends Model
{
    protected $fillable = [
        'venue_id', 'user_id', 'booking_id', 'rating', 'author', 'text',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'user_id' => 'integer',
            'booking_id' => 'integer',
            'rating' => 'decimal:1',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(EntertainmentVenue::class, 'venue_id', 'id');
    }
}
