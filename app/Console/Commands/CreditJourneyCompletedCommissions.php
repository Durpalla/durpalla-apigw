<?php

namespace App\Console\Commands;

use App\Services\AgentJourneyCommissionService;
use Illuminate\Console\Command;

class CreditJourneyCompletedCommissions extends Command
{
    protected $signature = 'commission:journey-complete {--limit=200 : Max bookings to reconcile}';

    protected $description = 'Reconcile, settle, void and reverse agent commission accruals';

    public function handle(AgentJourneyCommissionService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $stats = $service->reconcile($limit);
        $this->info(sprintf(
            'Accrued %d, settled %d, voided %d, reversed %d.',
            $stats['accrued'],
            $stats['settled'],
            $stats['voided'],
            $stats['reversed'],
        ));

        return self::SUCCESS;
    }
}
