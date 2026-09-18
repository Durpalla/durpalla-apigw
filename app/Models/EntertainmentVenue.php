<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EntertainmentVenue extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'merchant_id', 'name', 'slug', 'attraction_type', 'capacity_mode', 'city_id',
        'address', 'latitude', 'longitude', 'status', 'is_approved',
        'short_description', 'long_description', 'tagline', 'highlights', 'whats_included',
        'theme_preset', 'accent', 'logo_url', 'timezone', 'validity_days', 'max_redemptions',
        'default_daily_capacity', 'agent_commission_type', 'agent_commission',
        'rating_avg', 'review_count', 'opening_hours', 'cancellation_policy',
    ];

    protected function casts(): array
    {
        return [
            'merchant_id' => 'integer',
            'city_id' => 'integer',
            'status' => 'integer',
            'is_approved' => 'boolean',
            'highlights' => 'array',
            'whats_included' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
            'validity_days' => 'integer',
            'max_redemptions' => 'integer',
            'default_daily_capacity' => 'integer',
            'agent_commission' => 'decimal:2',
            'rating_avg' => 'decimal:2',
            'review_count' => 'integer',
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

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(EntertainmentTicketType::class, 'venue_id', 'id')->orderBy('sort_order');
    }

    public function slotTemplates(): HasMany
    {
        return $this->hasMany(EntertainmentSlotTemplate::class, 'venue_id', 'id')->orderBy('sort_order');
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(EntertainmentInventory::class, 'venue_id', 'id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(EntertainmentImage::class, 'venue_id', 'id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(EntertainmentReview::class, 'venue_id', 'id');
    }

    public function holds(): HasMany
    {
        return $this->hasMany(EntertainmentHold::class, 'venue_id', 'id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(EntertainmentTicket::class, 'venue_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1)->where('is_approved', true);
    }

    public function resolvedThemePreset(): string
    {
        $preset = trim((string) ($this->theme_preset ?? ''));
        if ($preset !== '') {
            return $preset;
        }

        return (string) ($this->attraction_type ?: 'other');
    }
}
