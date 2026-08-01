<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class DeckFare extends Model
{
    protected $fillable = [
        'route_id',
        'merchant_id',
        'vehicle_id',
        'vehicle_type',
        'departure_from',
        'departure_to',
        'fare',
        'reverse_fare',
        'type',
        'user_id',
        'service_charge',
        'service_charge_type',
        'is_active',
        'notes',
        'meta',
    ];

    protected $casts = [
        'fare' => 'float',
        'reverse_fare' => 'float',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    /**
     * Cached once per process — Schema::hasColumn on every decks eager-load
     * hammered information_schema and contributed to intermittent 500s.
     */
    private static ?bool $hasIsActiveColumn = null;

    private static function hasIsActiveColumn(): bool
    {
        if (self::$hasIsActiveColumn === null) {
            try {
                self::$hasIsActiveColumn = Schema::hasColumn('deck_fares', 'is_active');
            } catch (\Throwable) {
                self::$hasIsActiveColumn = false;
            }
        }

        return self::$hasIsActiveColumn;
    }

    /**
     * Exclude inactive planning fares when the column exists.
     */
    public function scopeActive(Builder $query): Builder
    {
        if (self::hasIsActiveColumn()) {
            $query->where(function (Builder $q) {
                $q->where('is_active', true)
                    ->orWhereNull('is_active');
            });
        }

        return $query;
    }

    public function isSellable(): bool
    {
        if (! self::hasIsActiveColumn()) {
            return true;
        }

        return $this->is_active !== false;
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(VehicleRoute::class, 'route_id', 'id');
    }

    public function launch(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'id');
    }

    public function departureFrom(): BelongsTo
    {
        return $this->belongsTo(RouteProperty::class, 'departure_from', 'id');
    }

    public function departureTo(): BelongsTo
    {
        return $this->belongsTo(RouteProperty::class, 'departure_to', 'id');
    }
}
