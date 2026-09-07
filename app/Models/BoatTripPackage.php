<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class BoatTripPackage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'boat_id',
        'title',
        'duration_hours',
        'base_price',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'boat_id' => 'integer',
            'duration_hours' => 'integer',
            'base_price' => 'decimal:2',
            'status' => 'integer',
        ];
    }

    public function boat(): BelongsTo
    {
        return $this->belongsTo(Boat::class, 'boat_id', 'id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(BoatTripPackageStop::class, 'package_id', 'id')->orderBy('sort_order');
    }

    public function stoppages(): HasManyThrough
    {
        return $this->hasManyThrough(
            BoatStoppage::class,
            BoatTripPackageStop::class,
            'package_id',
            'id',
            'id',
            'stoppage_id'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }
}
