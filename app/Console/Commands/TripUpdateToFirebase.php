<?php

namespace App\Console\Commands;

use App\Constants\AppConst;
use Illuminate\Console\Command;
use App\Services\FirebaseService;
use App\Services\TripService;
use App\Models\VehicleSchedule;

class TripUpdateToFirebase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trip:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Currently serving trip update to firebase for offline process';
    protected $firebase;
    private $trip;

    /**
     * Create a new command instance.
     *
     * @param FirebaseService $firebaseService
     * @param TripService $tripService
     */
    public function __construct(
        FirebaseService $firebaseService,
        TripService $tripService
    )
    {
        parent::__construct();
        $this->firebase = $firebaseService;
        $this->trip = $tripService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $startTime = date('Y-m-d H:i:s', strtotime("+5 hour"));
        $endTime = date('Y-m-d H:i:s');
        $trips = collect(VehicleSchedule::with(['route', 'decks', 'boardingVias.ghat', 'startFrom', 'stopTo', 'mappings.cabinType', 'vehicle', 'merchant'])
            ->where('status', AppConst::SCHEDULE_ACTIVE)
            ->where('leaving_at', '<=', $startTime)
            ->where('operation_timeline', '>=', $endTime)
            ->get());
        $trips->each(function ($trip, $key) use(&$layouts) {
            if($this->firebase->set('trip_layouts/' . $trip->id)->get() == null) {
                $this->firebase->set('trip_layouts/' . $trip->id)->update($this->trip->formatTriplayout($trip));
            }
            if($this->firebase->set('trips/' . $trip->vehicle['vehicle_type'] . '/' . $trip->id)->get() == null) {
                $this->firebase->set('trips/' . $trip->vehicle['vehicle_type'] . '/' . $trip->id)
                    ->update($this->trip->formatTripList($trip));
            }
        });
    }
}
