<?php

namespace App\Console\Commands;

use App\Services\AgentJourneyCommissionService;
use Illuminate\Console\Command;

class CreditJourneyCompletedCommissions extends Command
{
    protected $signature = 'commission:journey-complete {--limit=200 : Max booking items to process}';

    protected $description = 'Credit agent commissions for journey-completed (non-cancelled) booking items';

    public function handle(AgentJourneyCommissionService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $credited = $service->creditDueItems($limit);
        $this->info("Credited {$credited} commission row(s).");

        return self::SUCCESS;
    }
}
