<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Booking\Events\BookingFailedEvent;
use Modules\Booking\Events\BookingPendingHandleEvent;

class HandlePendingBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'booking:pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pending booking cancelled after expected time to failed payment';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $lockTime = getOption('payment_lock_period', 30) * 60;
        $expiresAt = time() + $lockTime;
        $bookings = Booking::with(['payment', 'bookingItems'])->where('status', 'PENDING')->where('created_at', '<=', date('Y-m-d H:i:s', $expiresAt))->get();
        if ($bookings) {
            try {
                $bookings->each(function ($booking) {
                    event(new BookingFailedEvent($booking));
//                    event(new BookingPendingHandleEvent($booking));
                });
            } catch (\Exception $e) {
                $this->info($e->getMessage());
            }
        }
    }
}
