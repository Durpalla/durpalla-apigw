<?php

namespace Modules\Vehicle\Jobs;

use App\Constants\AppConst;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;

class VehicleActiveSchedulePauseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    /**
     * @var Vehicle
     */
    private $vehicle;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Vehicle $vehicle)
    {
        $this->vehicle = $vehicle;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        VehicleSchedule::where('schedule_date', '>=', date('Y-m-d'))->where(['vehicle_id' => $this->vehicle->id, 'status' => AppConst::SCHEDULE_ACTIVE])
            ->update(['status' => AppConst::SCHEDULE_PAUSED]);
    }
}
