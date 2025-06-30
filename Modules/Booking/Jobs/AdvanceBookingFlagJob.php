<?php

namespace Modules\Booking\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Booking;
use App\Models\ScheduleCabinMapping;

class AdvanceBookingFlagJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 120;
    public $backoff = 15;
    public $tries = 5;
    public $maxExceptions = 3;
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
        $this->booking->bookingItems->each(function($item, $key) {
            if($item->booking_type !== 'deck') {
                ScheduleCabinMapping::where('id', $item->mapping_id)->update(['is_advance' => 1, 'booking_id' => $this->booking->id]);
            }
        });
    }
}
