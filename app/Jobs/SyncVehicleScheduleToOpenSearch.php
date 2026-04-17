<?php

namespace App\Jobs;

use App\Models\VehicleSchedule;
use App\Services\Search\OpenSearchTripClient;
use App\Services\Search\TripScheduleIndexDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncVehicleScheduleToOpenSearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $vehicleScheduleId) {}

    public function handle(OpenSearchTripClient $client): void
    {
        if (! $client->isConfigured()) {
            return;
        }

        $schedule = VehicleSchedule::query()->with(['vehicle', 'route'])->find($this->vehicleScheduleId);
        if (! $schedule) {
            $client->deleteDocument($this->vehicleScheduleId);

            return;
        }

        if (strtoupper((string) $schedule->status) !== \App\Constants\AppConst::SCHEDULE_ACTIVE) {
            $client->deleteDocument((int) $schedule->id);

            return;
        }

        $client->ensureIndex();
        $doc = TripScheduleIndexDocument::fromSchedule($schedule);
        $client->indexDocument((int) $schedule->id, $doc);
    }
}
