<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Cabin;
use App\Models\ScheduleCabinMapping;
use App\Models\VehicleSchedule;

class ScheduleCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 120;
    public $backoff = 15;
    public $tries = 5;
    public $maxExceptions = 3;
    private $schedule;

    /**
     * Create a new job instance.
     *
     * @param VehicleSchedule $schedule
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
        $items = Cabin::where(['vehicle_id' => $this->schedule->vehicle_id])->get();
        $mappings = [];
        if ($items) {
            foreach ($items as $item) {
                $mappings[] = [
                    'cabin_id' => $item->id,
                    'schedule_id' => $this->schedule->id,
                    'type' => $item->type,
                    'fare' => $item->fare,
                    'child_fare' => $item->child_fare,
                    'infant_fare' => $item->infant_fare,
                    'ownership' => $item->ownership,
                    'is_reserved' => $item->is_reserved,
                    'ghat_id' => $item->ghat_id,
                    'vehicle_id' => $item->vehicle_id,
                    'merchant_id' => $item->marchant_id,
                    'type_id' => $item->type_id,
                    'cabin_no' => $item->cabin_no,
                    'service_charge' => $item->service_charge,
                    'floor' => $item->floor,
                    'cabin_position' => $item->cabin_position,
                    'cabin_row' => $item->cabin_row,
                    'passenger_capacity' => $item->passenger_capacity
                ];
            }
            ScheduleCabinMapping::insert($mappings);
        }
    }
}
