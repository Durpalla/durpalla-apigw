<?php

namespace App\Services\Tour;

use App\Models\TourDeparture;

final class TourInventoryService
{
    public function availablePlaces(TourDeparture $departure): int
    {
        if ($departure->status !== TourDeparture::STATUS_OPEN) {
            return 0;
        }

        return max(
            0,
            (int) $departure->places_total - (int) $departure->places_sold - (int) $departure->places_held
        );
    }

    public function assertAvailability(TourDeparture $departure, int $places = 1): void
    {
        if ($departure->status !== TourDeparture::STATUS_OPEN) {
            throw new \RuntimeException('Departure is closed');
        }
        if ($this->availablePlaces($departure) < $places) {
            throw new \RuntimeException('Not enough places available');
        }
    }

    /**
     * Lock departure row and increment places_held (call inside a transaction).
     */
    public function applyHold(TourDeparture $departure, int $places = 1): void
    {
        $row = TourDeparture::query()
            ->whereKey($departure->id)
            ->lockForUpdate()
            ->first();
        if (! $row) {
            throw new \RuntimeException('Departure not found');
        }
        if ($row->status !== TourDeparture::STATUS_OPEN) {
            throw new \RuntimeException('Departure is closed');
        }
        $avail = (int) $row->places_total - (int) $row->places_sold - (int) $row->places_held;
        if ($avail < $places) {
            throw new \RuntimeException('Not enough places available');
        }
        $row->increment('places_held', $places);
    }

    public function releaseHold(TourDeparture $departure, int $places = 1): void
    {
        $row = TourDeparture::query()
            ->whereKey($departure->id)
            ->lockForUpdate()
            ->first();
        if ($row && (int) $row->places_held >= $places) {
            $row->decrement('places_held', $places);
        }
    }

    /**
     * After successful payment / confirm: move held → sold.
     */
    public function consumeHold(TourDeparture $departure, int $places = 1): void
    {
        $row = TourDeparture::query()
            ->whereKey($departure->id)
            ->lockForUpdate()
            ->first();
        if (! $row) {
            throw new \RuntimeException('Departure not found');
        }
        if ((int) $row->places_held < $places) {
            throw new \RuntimeException('Hold mismatch for departure');
        }
        $row->decrement('places_held', $places);
        $row->increment('places_sold', $places);
    }

    public function revertSold(TourDeparture $departure, int $places = 1): void
    {
        $row = TourDeparture::query()
            ->whereKey($departure->id)
            ->lockForUpdate()
            ->first();
        if ($row && (int) $row->places_sold >= $places) {
            $row->decrement('places_sold', $places);
        }
    }
}
