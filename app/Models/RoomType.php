<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomType extends Model
{
    public const CATEGORY_ROOM = 'room';
    public const CATEGORY_SUITE = 'suite';
    public const CATEGORY_APARTMENT = 'apartment';

    protected $fillable = [
        'name',
        'code',
        'category',
        'description',
    ];

    public static function categories(): array
    {
        return [
            self::CATEGORY_ROOM => 'Room',
            self::CATEGORY_SUITE => 'Suite',
            self::CATEGORY_APARTMENT => 'Apartment',
        ];
    }

    public function isSuite(): bool
    {
        return ($this->category ?? self::CATEGORY_ROOM) === self::CATEGORY_SUITE;
    }

    /**
     * Label for UI: "Standard" or "Suite · Presidential" style.
     */
    public function displayLabel(): string
    {
        $name = trim((string) $this->name);
        $category = $this->category ?? self::CATEGORY_ROOM;

        if ($category === self::CATEGORY_ROOM) {
            return $name;
        }

        // Avoid "Suite · Suite"
        if (strcasecmp($name, $category) === 0) {
            return ucfirst($category);
        }

        return ucfirst($category).' · '.$name;
    }

    public function rooms()
    {
        return $this->hasMany(HotelRoom::class, 'room_type_id', 'id');
    }

    public function ratePlans()
    {
        return $this->hasMany(RoomRatePlan::class, 'room_type_id', 'id');
    }

    public function bookingItems()
    {
        return $this->hasMany(BookingHotelItem::class, 'room_type_id', 'id');
    }
}
