<?php

namespace App\Services\Hotel\Contracts;

use App\Services\Hotel\DTO\HotelSearchRequestDTO;

interface HotelSupplierInterface
{
    /**
     * Search hotels for given criteria.
     */
    public function search(HotelSearchRequestDTO $request): array;

    /**
     * Get live availability and prices for a specific hotel/room selection.
     */
    public function getAvailability(array $criteria): array;

    /**
     * Recheck rate before booking (mandatory for suppliers).
     */
    public function recheckRate(string $rateKey): array;

    /**
     * Create a booking.
     */
    public function book(array $payload): array;

    /**
     * Cancel an existing booking.
     */
    public function cancel(string $supplierBookingReference, array $options = []): array;

    /**
     * Get booking status from supplier.
     */
    public function getBookingStatus(string $supplierBookingReference): array;

    /**
     * Get supplier code (e.g., 'local', 'ratehawk').
     */
    public function getSupplierCode(): string;
}
