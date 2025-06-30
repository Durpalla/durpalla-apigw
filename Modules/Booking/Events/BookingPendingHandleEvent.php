<?php

namespace Modules\Booking\Events;

use Illuminate\Queue\SerializesModels;
use App\Models\Booking;

class BookingPendingHandleEvent
{
    use SerializesModels;

    /**
     * @var Booking
     */
    public $booking;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }
}
