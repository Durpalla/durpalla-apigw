<?php

namespace App\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\FirebaseService;
use App\Models\VehicleSupervisor;

class SupervisorUpdateToFirebaseJob
{
    use Dispatchable;
    public $timeout = 120;
    public $backoff = 15;
    public $tries = 5;
    public $maxExceptions = 3;
    private $firebase;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebase = $firebaseService;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $supervisors = VehicleSupervisor::get();

        $this->firebase->set('devices')
            ->update(
                $supervisors->map(function($item, $key) {
                    return [
                        'id' => $item->user['id'],
                        'name' => $item->user['name'],
                        'vehicle_id' => $item->vehicle_id,
                        'vehicle_type' => $item->vehicle['vehicle_type'],
                        'is_master' => $item->is_master,
                        'master_id' => $item->master_id
                    ];
                })
            );
    }
}
