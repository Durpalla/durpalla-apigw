<?php

namespace Modules\Cancellation\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\BookingCancellation;

class CancellationRequestEmailToSupportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var BookingCancellation
     */
    private $cancellation;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(BookingCancellation $cancellation)
    {
        $this->cancellation = $cancellation;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

    }
}
