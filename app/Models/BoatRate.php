<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoatRate extends Model
{
    public const MODE_HOURLY = 'hourly';

    public const MODE_DAILY = 'daily';

    protected $fillable = [
        'boat_id',
        'rental_mode',
        'unit_price',
        'min_units',
        'max_units',
    ];

    protected function casts(): array
    {
        return [
            'boat_id' => 'integer',
            'unit_price' => 'decimal:2',
            'min_units' => 'integer',
            'max_units' => 'integer',
        ];
    }

    public function boat(): BelongsTo
    {
        return $this->belongsTo(Boat::class, 'boat_id', 'id');
    }
}
