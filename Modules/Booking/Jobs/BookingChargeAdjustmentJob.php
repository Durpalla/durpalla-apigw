<?php

namespace Modules\Booking\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Booking;
use App\Services\CalculationService;

class BookingChargeAdjustmentJob
{
    use Dispatchable, SerializesModels;

    /**
     * @var Booking
     */
    private $booking;
    /**
     * @var CalculationService
     */
    private $calculation;

    public function __construct(Booking $booking, CalculationService $calculationService)
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
        $total_discount = 0;
        $total_charge = 0;
        $total_amount = $this->booking->total_amount;
        $total_payable = $this->booking->total_payable;
        $total_vat = 0;
        $this->booking->bookingItems->each(function($item, $key) use(&$total_payable, &$total_amount, &$total_charge, &$total_discount, &$total_vat) {
            $itemCharge = $this->calculation->calculateItemCharge($item->toArray());
            $itemVat = $this->calculation->calculateItemVat($item->toArray());
            $totalDiscount = $this->calculation->calculateItemDiscount($item->toArray());
            $total_charge += $itemCharge;
            $total_payable += $itemCharge + $itemVat - $totalDiscount;
            $total_discount += $totalDiscount;
            $total_vat += $itemVat;
        });
        $this->booking->update([
            'charge_total' => $total_charge,
            'total_payable' => $total_payable,
            'total_amount' => $total_amount,
            'total_discount' => $total_discount,
            'vat_total' => $total_vat
        ]);
    }
}
