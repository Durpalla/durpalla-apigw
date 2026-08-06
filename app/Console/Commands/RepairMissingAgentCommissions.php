<?php

namespace App\Console\Commands;

use App\Services\AgentJourneyCommissionService;
use Illuminate\Console\Command;

class RepairMissingAgentCommissions extends Command
{
    protected $signature = 'commission:repair-missing
                            {--limit=100 : Max bookings to inspect/repair}
                            {--hours=1 : Only bookings checked/created within this many hours}
                            {--dry-run : List candidates without clearing locks or accruing}';

    protected $description = 'Detect recent agent bookings missing expected commission accruals and re-accrue them';

    public function handle(AgentJourneyCommissionService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $hours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');

        $stats = $service->repairMissingAccruals($limit, $dryRun, $hours);

        if ($stats['candidates'] === 0) {
            $this->info(sprintf('No missing agent commission candidates in the last %d hour(s).', $hours));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d booking(s) within last %d hour(s): %s',
            $dryRun ? 'Would repair' : 'Repaired',
            $stats['candidates'],
            $hours,
            implode(', ', $stats['booking_ids'])
        ));

        if (! $dryRun) {
            $this->info(sprintf(
                'Cleared locks on %d booking(s); created %d new accrual row(s).',
                $stats['repaired'],
                $stats['accrued']
            ));
        }

        return self::SUCCESS;
    }
}
