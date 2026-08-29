<?php

namespace App\Services\Hotel\DTO;

class HotelSearchRequestDTO
{
    public function __construct(
        public int $cityId,
        public string $checkIn,
        public string $checkOut,
        public int $adults,
        public int $children = 0,
        public ?array $roomOccupancies = null,
        public ?array $filters = [],
        public ?int $hotelId = null,
        public ?string $hotelName = null
    ) {}
}
