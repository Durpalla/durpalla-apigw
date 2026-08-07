<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingPnrService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillBookingPnrs extends Command
{
    protected $signature = 'booking:backfill-pnrs
                            {--chunk=200 : Number of bookings to process per chunk}
                            {--dry-run : Show how many would be updated without writing}';

    protected $description = 'Backfill random public PNRs for bookings that do not have one yet';

    public function handle(BookingPnrService $pnrService): int
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasColumn('bookings', 'pnr')) {
            $this->error('bookings.pnr column is missing. Run migrations first.');

            return self::FAILURE;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $skipped = 0;

        Booking::query()
            ->where(function ($q) {
                $q->whereNull('pnr')->orWhere('pnr', '');
            })
            ->orderBy('id')
            ->chunkById($chunk, function ($bookings) use ($pnrService, $dryRun, &$updated, &$skipped) {
                foreach ($bookings as $booking) {
                    if ($pnrService->isValid($booking->pnr ?? null)) {
                        $skipped++;
                        continue;
                    }

                    $pnr = $pnrService->generate($booking->booking_date ?: $booking->created_at);
                    if ($dryRun) {
                        $this->line("[dry-run] #{$booking->id} => {$pnr}");
                    } else {
                        $booking->forceFill(['pnr' => $pnr])->saveQuietly();
                    }
                    $updated++;
                }
            });

        $verb = $dryRun ? 'would update' : 'updated';
        $this->info("Backfill complete: {$verb} {$updated} booking(s), skipped {$skipped}.");

        return self::SUCCESS;
    }
}
