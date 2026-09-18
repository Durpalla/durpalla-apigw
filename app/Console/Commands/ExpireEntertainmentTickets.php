<?php

namespace App\Console\Commands;

use App\Models\EntertainmentTicket;
use App\Models\EntertainmentTicketEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ExpireEntertainmentTickets extends Command
{
    protected $signature = 'entertainment:expire-tickets';

    protected $description = 'Mark unused entertainment tickets past valid_until as expired';

    public function handle(): int
    {
        if (! Schema::hasTable('entertainment_tickets')) {
            $this->warn('entertainment_tickets table missing');

            return self::SUCCESS;
        }

        $count = 0;
        EntertainmentTicket::query()
            ->where('status', EntertainmentTicket::STATUS_VALID)
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', now())
            ->orderBy('id')
            ->chunkById(200, function ($tickets) use (&$count) {
                foreach ($tickets as $ticket) {
                    $ticket->update(['status' => EntertainmentTicket::STATUS_EXPIRED]);
                    EntertainmentTicketEvent::create([
                        'ticket_id' => $ticket->id,
                        'event_type' => 'expire',
                        'actor_type' => 'system',
                    ]);
                    $count++;
                }
            });

        $this->info("Expired {$count} tickets.");

        return self::SUCCESS;
    }
}
