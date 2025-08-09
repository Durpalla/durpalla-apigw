<?php

namespace App\Jobs;

use App\Constants\AppConst;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Vehicle;
use App\Services\FirebaseService;

class VehicleListUpdateToFirebase
{
    use Dispatchable;
    public $timeout = 120;
    public $backoff = 15;
    public $tries = 5;
    public $maxExceptions = 3;
    private $firebase;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebase = $firebaseService;
    }

    public function handle()
    {
        $vehicles = Vehicle::where('status', AppConst::LAUNCH_ACTIVE)->get();

        $this->firebase->set('vehicles')
            ->update(
                $vehicles->map(function($item, $key) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'type' => $item->vehicle_type
                    ];
                })
            );
    }
}
