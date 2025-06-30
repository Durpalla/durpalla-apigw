<?php

namespace Modules\Vehicle\Events;

use Illuminate\Queue\SerializesModels;
use App\Models\Vehicle;

class VehicleInactiveEvent
{
    use SerializesModels;

    /**
     * @var Vehicle
     */
    public $vehicle;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Vehicle $vehicle)
    {
        $this->vehicle = $vehicle;
    }
}
