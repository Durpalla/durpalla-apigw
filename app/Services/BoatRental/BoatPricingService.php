<?php

namespace App\Services\BoatRental;

use App\Models\BoatRate;
use App\Models\BoatTripPackage;
use Carbon\Carbon;

final class BoatPricingService
{
    /**
     * @return array{units:int,unit_price:float,total:float}
     */
    public function priceHourly(BoatRate $rate, Carbon $startsAt, Carbon $endsAt): array
    {
        $hours = max(1, (int) ceil($startsAt->diffInMinutes($endsAt) / 60));

        return $this->clampAndTotal($rate, $hours);
    }

    /**
     * @return array{units:int,unit_price:float,total:float}
     */
    public function priceDaily(BoatRate $rate, Carbon $startsAt, Carbon $endsAt): array
    {
        $hours = max(1.0, $startsAt->floatDiffInHours($endsAt));
        $days = max(1, (int) ceil($hours / 24));

        return $this->clampAndTotal($rate, $days);
    }

    /**
     * @return array{units:int,unit_price:float,total:float}
     */
    public function pricePackage(BoatTripPackage $package): array
    {
        $unit = round((float) $package->base_price, 2);

        return [
            'units' => 1,
            'unit_price' => $unit,
            'total' => $unit,
        ];
    }

    /**
     * @return array{units:int,unit_price:float,total:float}
     */
    private function clampAndTotal(BoatRate $rate, int $units): array
    {
        $min = max(1, (int) ($rate->min_units ?? 1));
        $max = $rate->max_units !== null ? (int) $rate->max_units : null;
        $units = max($min, $units);
        if ($max !== null && $max > 0) {
            $units = min($units, $max);
        }
        $unitPrice = round((float) $rate->unit_price, 2);
        $total = round($unitPrice * $units, 2);

        return [
            'units' => $units,
            'unit_price' => $unitPrice,
            'total' => $total,
        ];
    }
}
