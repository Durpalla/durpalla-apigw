<?php

namespace App\Jobs;

use App\Constants\AppConst;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\VehicleSchedule;

class VehicleActiveOnScheduleActiveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var VehicleSchedule
     */
    private $schedule;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(VehicleSchedule $schedule)
    {
        $this->schedule = $schedule;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->schedule->vehicle->update(['status' => AppConst::LAUNCH_ACTIVE]);
    }
}
