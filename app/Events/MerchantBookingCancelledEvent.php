<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MerchantBookingCancelledEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public const BROADCAST_AS = 'booking-cancelled';

    /**
     * @param  list<int>  $bookingItemIds
     */
    public function __construct(
        public string $merchantOwnerUserId,
        public string $bookingIdFormatted,
        /** @var list<int> */
        public array $bookingItemIds,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('merchant.'.$this->merchantOwnerUserId)];
    }

    public function broadcastAs(): string
    {
        return self::BROADCAST_AS;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'booking_id' => $this->bookingIdFormatted,
            'booking_item_ids' => $this->bookingItemIds,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
