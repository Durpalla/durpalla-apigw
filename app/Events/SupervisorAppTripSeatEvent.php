<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SupervisorAppTripSeatEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public const EVENT_LOCKED = 'seat.locked';

    public const EVENT_RELEASED = 'seat.released';

    public const EVENT_BOOKED = 'seat.booked';

    public const EVENT_CANCELLED = 'seat.cancelled';

    public const EVENT_TRIP_UPDATED = 'trip.updated';

    public static function broadcastSafely(string $tripSlug, string $seatId, string $eventName): void
    {
        try {
            broadcast(new self($tripSlug, $seatId, $eventName));
        } catch (\Throwable $e) {
            Log::warning('SupervisorAppTripSeatEvent: broadcast failed', [
                'trip_slug' => $tripSlug,
                'seat_id' => $seatId,
                'event' => $eventName,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function __construct(
        public string $tripSlug,
        public string $seatId,
        public string $broadcastEventName,
    ) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('trip.'.$this->tripSlug)];
    }

    public function broadcastAs(): string
    {
        return $this->broadcastEventName;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $payload = [
            'tripId' => $this->tripSlug,
            'timestamp' => now()->toIso8601String(),
        ];
        if ($this->seatId !== '') {
            $payload['seatId'] = $this->seatId;
        }

        return $payload;
    }
}
