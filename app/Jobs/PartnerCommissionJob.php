<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\AgentBalance;
use App\Models\AgentCommission;
use App\Models\Booking;
use App\Services\CalculationService;

class PartnerCommissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $backoff = 15;
    public $tries = 5;
    public $maxExceptions = 3;
    private $booking;
    private $calculation;

    /**
     * Create a new job instance.
     *
     * @param Booking $booking
     * @param CalculationService $calculationService
     */
    public function __construct(
        Booking $booking,
        CalculationService $calculationService
    )
    {
        $this->booking = $booking;
        $this->calculation = $calculationService;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->booking->bookingItems()->each(function ($item, $key) use (&$data) {
            if($item->vehicle && $item->vehicle->partners !== null) {
                $item->vehicle->partners->each(function($partner, $key) use ($item) {
                    $amount = $this->calculation->calculatePartnerCommission($item->price, $partner);
                    $commission = AgentCommission::firstOrNew([
                        'user_id' => $partner->id,
                        'type' => 'credit',
                        'purpose' => 'commission',
                        'commission_date' => date('Y-m-d')
                    ]);
                    $commission->total_sale += 1;
                    $commission->amount += $amount;
                    $commission->save();

                    $balance = AgentBalance::firstOrNew(['user_id' => $partner->id]);
                    $balance->balance += $amount;
                    $balance->save();
                });
            }
        });
    }
}
