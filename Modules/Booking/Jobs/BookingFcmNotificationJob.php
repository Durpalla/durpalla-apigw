<?php

namespace Modules\Booking\Jobs;

use App\Constants\AppConst;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Booking;
use App\Services\HelperService;
use Rajtika\Firebase\Services\Firebase;

class BookingFcmNotificationJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        if($this->booking->status == AppConst::BOOKING_COMPLETE && $this->booking->customer->device_id !== null && strlen($this->booking->customer->device_id) > 30) {
            $helper = new HelperService();
//            "eDBk8m2gQVKJl4heXNp6JF:APA91bHOm2V1bFPXvLtu4pmUG2xOf1Q6mcKE6ftfXO_UgwB8YC9Hhg88qpcQgDzbnRkXJhNwOz8hgzdrtsXvCD_0hI50HucL4688Sb50bfK-iNoCbDY7GW5Bde6nojqun4vHroWmUdiy"
//            $notification = Firebase::to($this->booking->customer->device_id)
//                ->setTitle('Ticket booking')
//                ->setBody(str_replace('%0A', ' ', $helper->getMessage($this->booking)))
//                ->setID($this->booking->id)
//                ->send('data');
        }
    }
}
