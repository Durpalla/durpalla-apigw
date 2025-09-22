<?php

namespace Modules\Booking\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Booking;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BookingQrcodeGenerateJob
{
    use Dispatchable;
    public $timeout = 120;
    public $backoff = 15;
    public $tries = 5;
    public $maxExceptions = 3;
    private $booking;
    private $code;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
        if($booking->officer->hasRole('customer')) {
            $this->code = (string) $booking->id;
        } else {
            $this->code = ($booking->payment->dues > 0) ? $booking->id . '@' . round($booking->payment->dues) : (string) $booking->id;
        }
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        QrCode::size(500)
            ->format('png')
            ->margin(0)
            // ->color(33, 152, 118)
            ->size(500)
            ->merge(public_path('default/logo-icon.png'), .1, true)
            ->generate($this->code, storage_path('app/public/qrs/' . $this->booking->id . '.png'));
    }
}
