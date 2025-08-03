<?php

namespace App\Jobs;

use App\Constants\AppConst;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Booking;
use App\Models\ScheduleCabinMapping;
use Illuminate\Support\Facades\Log;

class CabinMappingBookingJob
{
    use Dispatchable, InteractsWithQueue, SerializesModels, Queueable;

    /**
     * @var Booking
     */
    private $booking;
    private $status;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Booking $booking, $status)
    {
        $this->booking = $booking;
        $this->status = $status;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            collect($this->booking->bookingItems)->each(function ($item, $key) {
                if ($item->booking_type != 'deck') {
                    ScheduleCabinMapping::where(['cabin_id' => $item->cabin_id, 'schedule_id' => $item->trip_id])
                        ->update([
                            'booked' => AppConst::BOOKING_ITEM_ACTIVE,
                            'booking_id' => $item->booking_id,
                            'is_locked' => AppConst::BOOKING_ITEM_PENDING
                        ]);
                }
            });
        } catch (\Exception $e) {
            dd($e);
            Log::error($e->getMessage(), [
                'keyword' => 'SCHEDULE_CABIN_MAPPING_JOB',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }
}
