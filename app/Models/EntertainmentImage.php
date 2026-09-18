<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntertainmentImage extends Model
{
    protected $fillable = ['venue_id', 'url', 'type', 'sort_order'];

    protected function casts(): array
    {
        return [
            'venue_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(EntertainmentVenue::class, 'venue_id', 'id');
    }
}
