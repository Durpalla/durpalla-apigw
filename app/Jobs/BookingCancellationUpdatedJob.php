<?php

namespace App\Jobs;

use App\Constants\AppConst;
use App\Models\BookingCancellation;
use App\Notifications\CancellationRequestRefunded;
use App\Services\AgentPushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BookingCancellationUpdatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $backoff = 15;

    public int $tries = 5;

    public int $maxExceptions = 3;

    public function __construct(protected BookingCancellation $bookingCancellation)
    {
    }

    public function handle(AgentPushNotificationService $push): void
    {
        $this->bookingCancellation->loadMissing(['customer', 'officer', 'booking']);

        switch ($this->bookingCancellation->status) {
            case AppConst::CANCELLATION_APPROVED:
                $push->notifyCancellation($this->bookingCancellation, __('approved'));
                break;
            case AppConst::CANCELLATION_REJECTED:
                $push->notifyCancellation($this->bookingCancellation, __('rejected'));
                break;
            case AppConst::CANCELLATION_PROCESSING:
                $push->notifyCancellation($this->bookingCancellation, __('processing'));
                break;
            case AppConst::CANCELLATION_REFUNDED:
                if ($this->bookingCancellation->customer) {
                    $this->bookingCancellation->customer->notify(
                        new CancellationRequestRefunded($this->bookingCancellation)
                    );
                }
                $push->notifyRefund($this->bookingCancellation);
                break;
        }
    }
}
