<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelRoomType extends Model
{
    public const CATEGORY_ROOM = 'room';
    public const CATEGORY_SUITE = 'suite';
    public const CATEGORY_APARTMENT = 'apartment';

    protected $fillable = [
        'hotel_id', 'code', 'category', 'title', 'max_occupancy', 'bed_type', 'amenities',
        'base_price_per_night', 'currency', 'status', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'base_price_per_night' => 'decimal:2',
            'is_active' => 'bool',
        ];
    }

    /**
     * API label without a redundant "Room" suffix.
     */
    public function displayTitle(): string
    {
        $title = trim((string) $this->title);
        $title = trim(preg_replace('/\s+Room$/i', '', $title) ?? $title);

        return $title !== '' ? $title : 'Standard';
    }

    /**
     * Explicit accommodation category for clients that need to distinguish
     * normal rooms from suites/apartments.
     */
    public function accommodationCategory(): string
    {
        $category = strtolower(trim((string) ($this->category ?? '')));
        if (in_array($category, [
            self::CATEGORY_ROOM,
            self::CATEGORY_SUITE,
            self::CATEGORY_APARTMENT,
        ], true)) {
            return $category;
        }

        return str_contains(strtolower($this->displayTitle()), 'suite')
            ? self::CATEGORY_SUITE
            : self::CATEGORY_ROOM;
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(HotelRoomTypePhoto::class)->orderBy('sort_order');
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(HotelInventory::class);
    }
}
