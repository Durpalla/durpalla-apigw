<?php

namespace Modules\Payment\Events;

use Illuminate\Queue\SerializesModels;
use App\Models\Booking;
use App\Models\Payment;

class PaymentCompleteEvent
{
    use SerializesModels;

    /**
     * @var Payment
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
