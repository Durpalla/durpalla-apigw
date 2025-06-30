<?php

namespace Modules\Booking\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\BookingCancellation;

class BookingCancellationCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 120;
    public $backoff = 15;
    public $tries = 5;
    public $maxExceptions = 3;
    protected $bookingCancellation;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(BookingCancellation $bookingCancellation)
    {
        $this->bookingCancellation = $bookingCancellation;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
    }
}
