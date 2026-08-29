<?php

namespace App\Services\Hotel;

use App\Models\Hotel;
use App\Models\HotelChildPolicy;
use App\Models\RoomRatePlan;

class ChildRuleEngine
{
    protected Hotel $hotel;
    protected ?RoomRatePlan $ratePlan;
    protected float $adultPrice;
    protected array $childrenAges;
    protected int $nights;

    public function __construct(
        Hotel $hotel,
        ?RoomRatePlan $ratePlan,
        float $adultPrice,
        array $childrenAges,
        int $nights
    ) {
        $this->hotel = $hotel;
        $this->ratePlan = $ratePlan;
        $this->adultPrice = $adultPrice;
        $this->childrenAges = $childrenAges;
        $this->nights = $nights;
    }

    public function calculateChildPrice(): float
    {
        $totalChildPrice = 0;

        foreach ($this->childrenAges as $age) {
            $policy = $this->resolvePolicy((int) $age);

            if (! $policy) {
                $totalChildPrice += $this->adultPrice * $this->nights;
                continue;
            }

            $price = match ($policy->price_type) {
                'free' => 0,
                'fixed' => $policy->price_value * $this->nights,
                'percentage' => ($this->adultPrice * $policy->price_value / 100) * $this->nights,
                'adult' => $this->adultPrice * $this->nights,
                default => 0,
            };

            $totalChildPrice += $price;
        }

        return $totalChildPrice;
    }

    public function validate(): array
    {
        $errors = [];

        foreach ($this->childrenAges as $index => $age) {
            $policy = $this->resolvePolicy((int) $age);

            if (! $policy) {
                $errors[] = 'Child #'.($index + 1).' (age '.$age.') does not match any policy';
                continue;
            }

            if ($policy->bed_type === 'extra_bed' && ! $this->isExtraBedAvailable()) {
                $errors[] = 'Extra bed not available for child #'.($index + 1);
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    protected function resolvePolicy(int $age): ?HotelChildPolicy
    {
        $query = HotelChildPolicy::where('hotel_id', $this->hotel->id)
            ->where('min_age', '<=', $age)
            ->where('max_age', '>=', $age);

        if ($this->ratePlan) {
            $ratePlanPolicy = (clone $query)
                ->where('rate_plan_id', $this->ratePlan->id)
                ->first();

            if ($ratePlanPolicy) {
                return $ratePlanPolicy;
            }
        }

        return $query->whereNull('rate_plan_id')->first();
    }

    protected function isExtraBedAvailable(): bool
    {
        return true;
    }
}
