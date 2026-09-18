<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntertainmentTicketType extends Model
{
    protected $fillable = [
        'venue_id', 'code', 'name', 'min_age', 'max_age', 'base_price',
        'validity_days', 'max_redemptions', 'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'min_age' => 'integer',
            'max_age' => 'integer',
            'base_price' => 'decimal:2',
            'validity_days' => 'integer',
            'max_redemptions' => 'integer',
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
