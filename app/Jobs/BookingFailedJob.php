<?php

namespace App\Jobs;

use App\Constants\AppConst;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Booking;
use App\Models\ScheduleCabinMapping;

class BookingFailedJob
{
    use Dispatchable;

    /**
     * @var Booking
     */
    private $booking;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->booking->update(['status' => AppConst::BOOKING_FAILED]);
        $this->booking->bookingItems->each(function($item, $_key) {
            $item->update(['status' => AppConst::BOOKING_ITEM_CANCELLED]);
            if($item->booking_type != 'deck')
                ScheduleCabinMapping::where(['schedule_id' => $item->trip_id, 'cabin_id' => $item->cabin_id])
                    ->first()
                    ->update([
                        'booked' => AppConst::BOOKING_ITEM_PENDING,
                        'booking_id' => null
                    ]);

        });
    }
}
