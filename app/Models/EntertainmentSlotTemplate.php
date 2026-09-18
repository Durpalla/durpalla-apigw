<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntertainmentSlotTemplate extends Model
{
    protected $fillable = [
        'venue_id', 'label', 'starts_at', 'ends_at', 'capacity', 'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'capacity' => 'integer',
            'status' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(EntertainmentVenue::class, 'venue_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
