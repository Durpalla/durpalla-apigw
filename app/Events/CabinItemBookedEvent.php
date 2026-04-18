<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CabinItemBookedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $tripId,
        public int $itemId,
        public array $data,
        public $userId = null,
        public ?int $merchantId = null,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("trip.{$this->tripId}");
    }

    public function broadcastAs(): string
    {
        return 'item.booked';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->tripId,
            'item_id' => $this->itemId,
            'merchant_id' => $this->merchantId,
            'user_id' => $this->userId,
            'data' => $this->data,
        ];
    }
}
