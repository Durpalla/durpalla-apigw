<?php

namespace App\Services\Promotion\DTO;

/**
 * Snapshot of the cart/booking being evaluated for promotions.
 *
 * `items` is a list of associative arrays, each describing one cart/booking
 * line so the engine can match it against `promotion_targets` without any
 * service-specific switch statements:
 *
 *   [
 *       'type'        => 'cabin'|'seat'|'deck'|'room',
 *       'amount'      => 1200.0,
 *       'merchant_id' => 5,
 *       'route_id'    => 12,
 *       'vehicle_id'  => 8,
 *       'schedule_id' => 44,
 *       'customer_id' => 1001,
 *       'hotel_id'    => null,
 *       'city_id'     => null,
 *   ]
 */
class PromotionContext
{
    public function __construct(
        public readonly ?int $userId,
        public readonly string $serviceType,
        public readonly string $channel,
        public readonly array $items,
        public readonly float $subtotal,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $items = $data['items'] ?? [];
        $subtotal = $data['subtotal'] ?? array_sum(array_column($items, 'amount'));

        return new self(
            userId: $data['user_id'] ?? null,
            serviceType: $data['service_type'] ?? 'transport',
            channel: $data['channel'] ?? 'all',
            items: $items,
            subtotal: (float) $subtotal,
        );
    }
}
