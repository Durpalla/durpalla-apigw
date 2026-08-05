<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Booking;
use App\Models\AgentBalance;
use App\Models\AgentCommission;
use App\Services\CalculationService;

class BookingCommissionCalculationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $backoff = 15;
    public $tries = 5;
    public $maxExceptions = 3;
    private $booking;
    private $calculation;
    private $officer;

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
        // Disabled: durable accruals are exclusively owned by commission:journey-complete.
        return;

        $data = ['total_sale' => 0, 'amount' => 0];
        $this->booking->bookingItems()->each(function ($item, $key) use (&$data) {
            $data['total_sale'] += $item->price - $item->discount;
            $data['amount'] += $this->calculation->calculateAgentCommission($item);
        });

        if($data['amount']) {
            $commission = AgentCommission::firstOrNew(['user_id' => $this->booking->user_id, 'type' => 'credit', 'purpose' => 'commission', 'commission_date' => date('Y-m-d')]);
            $commission->total_sale += $data['total_sale'];
            $commission->amount += $data['amount'];
            $commission->save();

            $balance = AgentBalance::firstOrNew(['user_id' => $this->booking->user_id]);
            $balance->balance += $data['amount'];
            $balance->save();
        }
    }
}
