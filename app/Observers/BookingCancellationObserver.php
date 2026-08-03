<?php

namespace App\Observers;

use App\Constants\AppConst;
use Illuminate\Support\Facades\DB;
use App\Models\BookingCancellation;
use App\Services\CancellationService;
use App\Jobs\BookingCancellationCreatedJob;
use App\Jobs\BookingCancellationUpdatedJob;
use App\Jobs\BookingCancelledCabinRealeseJob;
use App\Jobs\RefundExecutionJob;

class BookingCancellationObserver
{
    private $cancellationService;
    public function __construct(CancellationService $cancellationService)
    {
        $this->cancellationService = $cancellationService;
    }

    /**
     * Handle the booking cancellation "created" event.
     *
     * @param  BookingCancellation  $bookingCancellation
     * @return void
     */
    public function created(BookingCancellation $bookingCancellation)
    {
        dispatch(new BookingCancellationCreatedJob($bookingCancellation));
    }

    /**
     * Handle the booking cancellation "updated" event.
     *
     * @param  BookingCancellation  $bookingCancellation
     * @return void
     */
    public function updated(BookingCancellation $bookingCancellation)
    {
        DB::table('booking_cancellation_items')->where('booking_cancellation_id', $bookingCancellation->id)->update([
            'status' => $bookingCancellation->status
        ]);
        if ($bookingCancellation->status == AppConst::CANCELLATION_APPROVED
            && (int) $bookingCancellation->getOriginal('status') !== AppConst::CANCELLATION_APPROVED) {
            $this->cancellationService->afterApproved($bookingCancellation);
            if ($bookingCancellation->booking) {
                dispatch(new BookingCancelledCabinRealeseJob($bookingCancellation->booking));
            }
            dispatch(new RefundExecutionJob((int) $bookingCancellation->id));
        }
        dispatch(new BookingCancellationUpdatedJob($bookingCancellation));
    }
}
