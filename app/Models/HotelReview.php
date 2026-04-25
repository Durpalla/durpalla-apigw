<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelReview extends Model
{
    public $timestamps = false;

    protected $fillable = ['hotel_id', 'user_id', 'author', 'rating', 'body', 'reviewed_at'];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:1',
            'reviewed_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
