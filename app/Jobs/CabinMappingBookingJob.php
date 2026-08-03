<?php

namespace App\Jobs;

use App\Constants\AppConst;
use App\Models\Booking;
use App\Models\ScheduleCabinMapping;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CabinMappingBookingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Booking $booking;

    private int $status;

    public function __construct(Booking $booking, int $status)
    {
        $this->booking = $booking;
        $this->status = $status;
    }

    public function handle(): void
    {
        if ((int) $this->status !== AppConst::BOOKING_ITEM_ACTIVE) {
            return;
        }

        try {
            $this->booking->loadMissing('bookingItems');
            foreach ($this->booking->bookingItems as $item) {
                if (strtolower((string) $item->booking_type) === 'deck') {
                    continue;
                }

                $payload = [
                    'booked' => 1,
                    'booking_id' => $item->booking_id,
                    'is_locked' => 0,
                ];

                if (! empty($item->mapping_id)) {
                    ScheduleCabinMapping::query()
                        ->where('id', (int) $item->mapping_id)
                        ->update($payload);
                    continue;
                }

                ScheduleCabinMapping::query()
                    ->where('cabin_id', $item->cabin_id)
                    ->where('schedule_id', $item->trip_id)
                    ->update($payload);
            }
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), [
                'keyword' => 'SCHEDULE_CABIN_MAPPING_JOB',
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
}
