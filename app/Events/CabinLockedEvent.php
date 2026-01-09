<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CabinLockedEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels, Queueable;
    public int $tripId;
    public int $itemId;
    public ?int $userId;

    public function __construct(int $tripId, int $itemId, $userId = null)
    {
        $this->tripId = $tripId;
        $this->itemId = $itemId;
        $this->userId = $userId;
    }

    /** Channel name */
    public function broadcastOn(): Channel
    {
        \Log::info('CabinLockedEvent broadcastOn called', [
            'trip_id' => $this->tripId,
            'item_id' => $this->itemId,
        ]);
        return new Channel("trip.{$this->tripId}");
    }

    public function broadcastAs(): string
    {
        return 'cart.item.locked';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->tripId,
            'item_id' => $this->itemId,
            'user_id' => $this->userId,
        ];
    }
}
