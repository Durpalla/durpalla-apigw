<?php

namespace Modules\Booking\Events;

use Illuminate\Foundation\Events\Dispatchable;
use App\Models\Booking;

class BookingCompleteEvent
{
    use Dispatchable;

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
