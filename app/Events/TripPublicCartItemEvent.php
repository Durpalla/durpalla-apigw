<?php

namespace App\Events;

use App\Models\ScheduleCabinMapping;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Public Pusher channel `trip.{numericScheduleId}` — layout sync for lock/reserve/book/cancel.
 */
class TripPublicCartItemEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public const EVENT_LOCKED = 'item.locked';

    public const EVENT_RESERVED = 'item.reserved';

    public const EVENT_RELEASED = 'item.released';

    public const EVENT_BOOKED = 'item.booked';

    public const EVENT_BOOKING_CANCELLED = 'item.booking_cancelled';

    public function __construct(
        public int $tripNumericId,
        public int $itemId,
        public ?int $merchantId,
        public ?int $userId,
        public array $data,
        public string $broadcastEventName,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('trip.'.$this->tripNumericId)];
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
        return [
            'trip_id' => $this->tripNumericId,
            'item_id' => $this->itemId,
            'merchant_id' => $this->merchantId,
            'user_id' => $this->userId,
            'data' => $this->data,
        ];
    }

    public static function broadcastSafely(
        string $broadcastEventName,
        int $tripNumericId,
        ScheduleCabinMapping $mapping,
        ?int $userId = null,
    ): void {
        try {
            $mapping->loadMissing('cabinType', 'cabin.cabinType', 'schedule');
            $letter = strtoupper((string) ($mapping->cabinType?->letter ?? $mapping->cabin?->cabinType?->letter ?? ''));
            $no = trim((string) ($mapping->cabin?->cabin_no ?? ''));
            $data = [
                'cabin_id' => (int) $mapping->cabin_id,
                'fare' => (int) round((float) ($mapping->fare ?? 0)),
                'cabin_no' => $letter.$no,
            ];
            $merchantId = $mapping->merchant_id;
            if ($merchantId === null || (int) $merchantId === 0) {
                $merchantId = $mapping->schedule?->merchant_id;
            }
            $merchantId = ($merchantId !== null && (int) $merchantId > 0) ? (int) $merchantId : null;

            broadcast(new self(
                $tripNumericId,
                (int) $mapping->id,
                $merchantId,
                $userId,
                $data,
                $broadcastEventName,
            ));
        } catch (\Throwable $e) {
            Log::warning('TripPublicCartItemEvent: broadcast failed', [
                'trip_id' => $tripNumericId,
                'event' => $broadcastEventName,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public static function broadcastBookingItemCancelled(
        int $tripNumericId,
        ScheduleCabinMapping $mapping,
        int $bookingItemId,
        ?int $userId = null,
    ): void {
        try {
            $mapping->loadMissing('cabinType', 'cabin.cabinType', 'schedule');
            $letter = strtoupper((string) ($mapping->cabinType?->letter ?? $mapping->cabin?->cabinType?->letter ?? ''));
            $no = trim((string) ($mapping->cabin?->cabin_no ?? ''));
            $data = [
                'cabin_id' => (int) $mapping->cabin_id,
                'fare' => (int) round((float) ($mapping->fare ?? 0)),
                'cabin_no' => $letter.$no,
                'booking_item_id' => $bookingItemId,
            ];
            $merchantId = $mapping->merchant_id;
            if ($merchantId === null || (int) $merchantId === 0) {
                $merchantId = $mapping->schedule?->merchant_id;
            }
            $merchantId = ($merchantId !== null && (int) $merchantId > 0) ? (int) $merchantId : null;

            broadcast(new self(
                $tripNumericId,
                (int) $mapping->id,
                $merchantId,
                $userId,
                $data,
                self::EVENT_BOOKING_CANCELLED,
            ));
        } catch (\Throwable $e) {
            Log::warning('TripPublicCartItemEvent: booking_cancelled broadcast failed', [
                'trip_id' => $tripNumericId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
