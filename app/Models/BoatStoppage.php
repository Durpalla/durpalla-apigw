<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BoatStoppage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'merchant_id',
        'city_id',
        'name',
        'description',
        'latitude',
        'longitude',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'merchant_id' => 'integer',
            'city_id' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'status' => 'integer',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id', 'id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }
}
