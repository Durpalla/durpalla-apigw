<?php

namespace App\Events;

use App\Models\Booking;
use App\Models\VehicleSchedule;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MerchantLiveBookingEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $merchantOwnerId,
        public array $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('merchant.'.$this->merchantOwnerId)];
    }

    public function broadcastAs(): string
    {
        return 'new-booking';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }

    public static function dispatchFromBooking(
        VehicleSchedule $trip,
        Booking $booking,
        int $seatCount,
        string $passengerName,
        float $totalAmount,
        string $bookingIdFormatted,
    ): void {
        $merchantOwnerId = (string) ($trip->merchant_id ?? '');
        if ($merchantOwnerId === '' || (int) $merchantOwnerId === 0) {
            return;
        }

        $tripSlug = 'TRIP-'.str_pad((string) $trip->id, 3, '0', STR_PAD_LEFT);

        $booking->loadMissing('payment');
        $paid = (float) ($booking->payment?->paid_amount ?? 0);
        $due = max(0.0, $totalAmount - $paid);

        $payload = [
            'id' => 'live-'.$booking->id.'-'.time(),
            'trip_id' => $tripSlug,
            'property_name' => (string) ($trip->vehicle?->name ?? '—'),
            'passenger_name' => $passengerName,
            'seat_count' => $seatCount,
            'amount' => number_format($totalAmount, 0, '.', ''),
            'timestamp' => now()->toIso8601String(),
            'booking_id' => $bookingIdFormatted,
            'booking_status' => strtolower((string) $booking->status),
            'due_amount' => (int) round($due),
        ];

        broadcast(new self($merchantOwnerId, $payload));
    }

    public static function dispatchForCompletedBooking(Booking $booking): void
    {
        $booking->loadMissing(['bookingItems.trip.vehicle']);
        if ($booking->bookingItems->isEmpty()) {
            return;
        }

        $itemsByTrip = $booking->bookingItems->groupBy('trip_id');
        foreach ($itemsByTrip as $tripId => $items) {
            if ($tripId === null || (int) $tripId === 0) {
                continue;
            }
            $first = $items->first();
            $trip = $first->trip;
            if (! $trip instanceof VehicleSchedule) {
                continue;
            }
            $trip->loadMissing('vehicle');
            $seatCount = $items->count();
            $passengerName = 'Guest';
            if ($first->passenger) {
                $p = json_decode((string) $first->passenger, true);
                if (is_array($p) && ! empty($p['name'])) {
                    $passengerName = (string) $p['name'];
                }
            }
            $totalAmount = (float) $items->sum(fn ($i) => (float) $i->price);
            $bookingIdStr = method_exists($booking, 'publicReference')
                ? $booking->publicReference()
                : (string) $booking->id;

            self::dispatchFromBooking($trip, $booking, $seatCount, $passengerName, $totalAmount, $bookingIdStr);
        }
    }
}
