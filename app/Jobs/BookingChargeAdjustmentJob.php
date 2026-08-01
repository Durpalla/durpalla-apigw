<?php

namespace App\Jobs;

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
     * Recompute charge / VAT (on charge only) / discount / payable from booking items.
     */
    public function handle()
    {
        $total_discount = 0;
        $total_charge = 0;
        $total_vat = 0;
        $total_amount = 0;

        $this->booking->bookingItems->each(function ($item) use (&$total_charge, &$total_discount, &$total_vat, &$total_amount) {
            $row = $item->toArray();
            $total_amount += abs($row['price'] ?? 0);
            $total_charge += (float) $this->calculation->calculateItemCharge($row);
            $total_vat += (float) $this->calculation->calculateItemVat($row);
            $total_discount += (float) $this->calculation->calculateItemDiscount($row);
        });

        $total_payable = abs(($total_amount + $total_charge + $total_vat) - $total_discount);

        $this->booking->update([
            'charge_total' => $total_charge,
            'total_payable' => $total_payable,
            'total_amount' => $total_amount,
            'total_discount' => $total_discount,
            'vat_total' => $total_vat,
        ]);
    }
}
