<?php

namespace App\Console\Commands;

use App\Services\BoatRental\BoatTripBidService;
use Illuminate\Console\Command;

class ExpireBoatTripRequestsCommand extends Command
{
    protected $signature = 'boat-rental:expire-requests';

    protected $description = 'Mark expired open boat trip RFQs as expired';

    public function handle(BoatTripBidService $bids): int
    {
        $count = $bids->expireOpenRequests();
        $this->info("Expired {$count} boat trip request(s).");

        return self::SUCCESS;
    }
}
