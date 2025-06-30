<?php

namespace Modules\Booking\Jobs;

use App\Constants\AppConst;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\BookingCancellation;
use App\Notifications\CancellationApprovalNotificationToCustomer;
use App\Notifications\CancellationApprovalNotifyToSupervisor;
use App\Notifications\CancellationRejectedNotificationToCustomer;
use App\Notifications\CancellationRejectedNotificationToSupervisor;
use App\Notifications\CancellationRequestProcessing;
use App\Notifications\CancellationRequestRefunded;

class BookingCancellationUpdatedJob implements ShouldQueue
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
        switch ($this->bookingCancellation->status) {
            case AppConst::CANCELLATION_APPROVED :
                if($this->bookingCancellation->customer) {
                    $this->bookingCancellation->customer->notify(new CancellationApprovalNotificationToCustomer($this->bookingCancellation));
                }
                if($this->bookingCancellation->customer_id != $this->bookingCancellation->user_id && $this->bookingCancellation->officer) {
                    $this->bookingCancellation->officer->notify(new CancellationApprovalNotifyToSupervisor($this->bookingCancellation));
                }
                break;
            case AppConst::CANCELLATION_REJECTED:
                if($this->bookingCancellation->customer) {
                    $this->bookingCancellation->customer->notify(new CancellationRejectedNotificationToCustomer($this->bookingCancellation));
                }
                if($this->bookingCancellation->customer_id != $this->bookingCancellation->user_id && $this->bookingCancellation->officer) {
                    $this->bookingCancellation->officer->notify(new CancellationRejectedNotificationToSupervisor($this->bookingCancellation));
                }
                break;
            case AppConst::CANCELLATION_PROCESSING :
                if($this->bookingCancellation->customer) {
                    $this->bookingCancellation->customer->notify(new CancellationRequestProcessing($this->bookingCancellation));
                }
                break;
            case AppConst::CANCELLATION_REFUNDED :
                if($this->bookingCancellation->customer) {
                    $this->bookingCancellation->customer->notify(new CancellationRequestRefunded($this->bookingCancellation));
                }
                break;
        }
    }
}
