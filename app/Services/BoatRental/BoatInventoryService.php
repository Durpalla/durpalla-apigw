<?php

namespace App\Services\BoatRental;

use App\Constants\AppConst;
use App\Models\BoatHold;
use App\Models\BookingBoatItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

final class BoatInventoryService
{
    public function isAvailable(int $boatId, Carbon $startsAt, Carbon $endsAt, ?int $ignoreHoldId = null): bool
    {
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            return false;
        }

        if (Schema::hasTable('boat_holds')) {
            $holdQ = BoatHold::query()
                ->where('boat_id', $boatId)
                ->where('status', BoatHold::STATUS_PENDING)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt);
            if ($ignoreHoldId) {
                $holdQ->where('id', '!=', $ignoreHoldId);
            }
            if ($holdQ->exists()) {
                return false;
            }
        }

        if (Schema::hasTable('booking_boat_items') && Schema::hasTable('bookings')) {
            $booked = BookingBoatItem::query()
                ->where('boat_id', $boatId)
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->whereHas('booking', function ($bq) {
                    $bq->where('service_type', 'boat_rental')
                        ->whereNotIn('status', [
                            AppConst::BOOKING_CANCELLED,
                            'FAILED',
                            'failed',
                            'cancelled',
                        ]);
                })
                ->exists();
            if ($booked) {
                return false;
            }
        }

        return true;
    }

    public function assertAvailable(int $boatId, Carbon $startsAt, Carbon $endsAt, ?int $ignoreHoldId = null): void
    {
        if (! $this->isAvailable($boatId, $startsAt, $endsAt, $ignoreHoldId)) {
            throw new \RuntimeException('Boat is not available for the selected period');
        }
    }

    public function assertCapacity(int $capacityMax, int $guests): void
    {
        if ($guests < 1) {
            throw new \InvalidArgumentException('Guests must be at least 1');
        }
        if ($guests > $capacityMax) {
            throw new \InvalidArgumentException('Guests exceed boat capacity');
        }
    }
}
