<?php

namespace App\Console\Commands;

use App\Constants\AppConst;
use App\Models\VehicleSchedule;
use App\Services\Search\OpenSearchTripClient;
use App\Services\Search\TripScheduleIndexDocument;
use Illuminate\Console\Command;

class ReindexTripSearchCommand extends Command
{
    protected $signature = 'trip-search:reindex {--chunk=200 : Rows per bulk batch}';

    protected $description = 'Create OpenSearch index if missing and bulk-index active vehicle schedules';

    public function handle(OpenSearchTripClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->error('OpenSearch is disabled or OPENSEARCH_BASE_URL is empty. Set OPENSEARCH_ENABLED=true and base URL.');

            return self::FAILURE;
        }

        if (! $client->ensureIndex()) {
            $this->error('Failed to create or verify OpenSearch index.');

            return self::FAILURE;
        }

        $chunk = max(50, (int) $this->option('chunk'));
        $index = (string) config('trip_search.opensearch.index', 'durpalla_trip_schedules');
        $base = rtrim((string) config('trip_search.opensearch.base_url', ''), '/');
        $timeout = (int) config('trip_search.opensearch.timeout', 5);

        $count = 0;
        VehicleSchedule::query()
            ->where('status', AppConst::SCHEDULE_ACTIVE)
            ->with(['vehicle', 'route'])
            ->orderBy('id')
            ->chunkById($chunk, function ($schedules) use ($index, $base, $timeout, &$count) {
                $lines = '';
                foreach ($schedules as $schedule) {
                    $doc = TripScheduleIndexDocument::fromSchedule($schedule);
                    $id = (int) $schedule->id;
                    $lines .= json_encode(['index' => ['_index' => $index, '_id' => (string) $id]])."\n";
                    $lines .= json_encode($doc)."\n";
                    $count++;
                }
                $url = $base.'/_bulk';
                $req = \Illuminate\Support\Facades\Http::timeout($timeout)
                    ->withHeaders(['Content-Type' => 'application/x-ndjson']);
                $user = (string) config('trip_search.opensearch.username', '');
                $pass = (string) config('trip_search.opensearch.password', '');
                if ($user !== '' || $pass !== '') {
                    $req = $req->withBasicAuth($user, $pass);
                }
                $res = $req->withBody($lines, 'application/x-ndjson')->post($url);
                if (! $res->successful()) {
                    $this->warn('Bulk batch failed: '.$res->status().' '.$res->body());
                }
            });

        $this->info("Indexed {$count} schedules into {$index}.");

        return self::SUCCESS;
    }
}
