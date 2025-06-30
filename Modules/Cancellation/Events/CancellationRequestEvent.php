<?php

namespace Modules\Cancellation\Events;

use Illuminate\Queue\SerializesModels;
use App\Models\BookingCancellation;

class CancellationRequestEvent
{
    use SerializesModels;

    /**
     * @var BookingCancellation
     */
    public $cancellation;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(BookingCancellation $cancellation)
    {
        $this->cancellation = $cancellation;
    }

    /**
     * Get the channels the event should be broadcast on.
     *
     * @return array
     */
    public function broadcastOn(): array
    {
        return [];
    }
}
