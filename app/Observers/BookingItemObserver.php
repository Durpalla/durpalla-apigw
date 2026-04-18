<?php

namespace App\Observers;

use App\Constants\AppConst;
use App\Events\CabinItemBookedEvent;
use App\Models\BookingItem;
use App\Models\ScheduleCabinMapping;

class BookingItemObserver
{
    public function created(BookingItem $item): void
    {
        if ((int) $item->status === AppConst::BOOKING_ITEM_ACTIVE) {
            $this->maybeBroadcastBooked($item);
        }
    }

    public function updated(BookingItem $item): void
    {
        if (! $item->wasChanged('status')) {
            return;
        }
        if ((int) $item->status !== AppConst::BOOKING_ITEM_ACTIVE) {
            return;
        }
        if ((int) $item->getOriginal('status') === AppConst::BOOKING_ITEM_ACTIVE) {
            return;
        }
        $this->maybeBroadcastBooked($item);
    }

    private function maybeBroadcastBooked(BookingItem $item): void
    {
        $type = strtolower((string) $item->booking_type);
        if ($type === 'deck') {
            return;
        }

        $mapping = null;
        if (! empty($item->mapping_id)) {
            $mapping = ScheduleCabinMapping::query()
                ->with(['cabinType', 'cabin', 'schedule'])
                ->find((int) $item->mapping_id);
        }
        if (! $mapping && $item->trip_id && $item->cabin_id) {
            $mapping = ScheduleCabinMapping::query()
                ->with(['cabinType', 'cabin', 'schedule'])
                ->where('schedule_id', $item->trip_id)
                ->where('cabin_id', $item->cabin_id)
                ->first();
        }
        if (! $mapping instanceof ScheduleCabinMapping) {
            return;
        }

        $merchantId = $mapping->merchant_id;
        if ($merchantId === null || (int) $merchantId === 0) {
            $mapping->loadMissing('schedule');
            $merchantId = $mapping->schedule?->merchant_id;
        }
        $merchantId = ($merchantId !== null && (int) $merchantId > 0) ? (int) $merchantId : null;

        $data = $mapping->only(['cabin_id', 'fare']) + [
            'cabin_no' => strtoupper((string) ($mapping->cabinType?->letter ?? '')).$mapping->cabin?->cabin_no,
        ];

        broadcast(new CabinItemBookedEvent(
            (int) $item->trip_id,
            (int) $mapping->id,
            $data,
            null,
            $merchantId,
        ))->toOthers();
    }
}
