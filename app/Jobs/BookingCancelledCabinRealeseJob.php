<?php

namespace App\Jobs;

use App\Constants\AppConst;
use App\Models\Booking;
use App\Models\ScheduleCabinMapping;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCancelledCabinRealeseJob
{
    use Dispatchable, SerializesModels;

    public function __construct(private readonly Booking $booking)
    {
    }

    public function handle(): void
    {
        $this->booking->loadMissing('bookingItems');

        $this->booking->bookingItems->each(function ($item): void {
            $mapping = ScheduleCabinMapping::query()
                ->where('schedule_id', $item->trip_id)
                ->where('cabin_id', $item->cabin_id)
                ->first();

            if (! $mapping) {
                return;
            }

            $mapping->update([
                'booked' => AppConst::BOOKING_ITEM_PENDING,
                'booking_id' => null,
            ]);
        });
    }
}
