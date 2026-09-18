<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingEntertainmentItem extends Model
{
    protected $fillable = [
        'booking_id', 'venue_id', 'hold_id', 'visit_date', 'slot_template_id',
        'venue_name', 'attraction_type', 'lines_json', 'total_qty', 'unit_price', 'total_price',
        'validity_days', 'valid_from', 'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'booking_id' => 'integer',
            'venue_id' => 'integer',
            'hold_id' => 'integer',
            'visit_date' => 'date',
            'slot_template_id' => 'integer',
            'lines_json' => 'array',
            'total_qty' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'validity_days' => 'integer',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(EntertainmentVenue::class, 'venue_id', 'id');
    }
}
