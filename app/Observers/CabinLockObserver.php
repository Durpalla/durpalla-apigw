<?php

namespace App\Observers;

use App\Constants\AppConst;
use App\Events\CabinLockedEvent;
use App\Events\CabinReleasedEvent;
use App\Models\CabinLock;
use App\Models\ScheduleCabinMapping;

class CabinLockObserver
{
    public function created(CabinLock $cabinLock)
    {
        if ($cabinLock->mapping_id) {
            ScheduleCabinMapping::where('id', $cabinLock->mapping_id)
                ->first()
                ?->update(['is_locked' => AppConst::BOOKING_ITEM_ACTIVE]);
        } else {
            ScheduleCabinMapping::where(['cabin_id' => $cabinLock->cabin_id, 'schedule_id' => $cabinLock->trip_id])
                ->first()
                ?->update(['is_locked' => AppConst::BOOKING_ITEM_ACTIVE]);
        }

        $mapping = $cabinLock->mapping()->with(['cabinType', 'cabin', 'schedule'])->first();
        if (! $mapping instanceof ScheduleCabinMapping) {
            return;
        }

        $merchantId = $this->resolveMerchantId($mapping);
        $itemId = (int) ($cabinLock->mapping_id ?: $mapping->id);
        broadcast(new CabinLockedEvent(
            (int) $cabinLock->trip_id,
            $itemId,
            $this->mappingBroadcastData($mapping),
            null,
            $merchantId,
        ))->toOthers();
    }

    public function updated(CabinLock $cabinLock)
    {
        //
    }

    public function deleted(CabinLock $cabinLock)
    {
        $mapping = $cabinLock->mapping()->with(['cabinType', 'cabin', 'schedule'])->first();
        if ($cabinLock->mapping_id) {
            ScheduleCabinMapping::find($cabinLock->mapping_id)
                ?->update(['is_locked' => AppConst::BOOKING_ITEM_PENDING]);
        } else {
            ScheduleCabinMapping::where(['cabin_id' => $cabinLock->cabin_id, 'schedule_id' => $cabinLock->trip_id])
                ->first()
                ?->update(['is_locked' => AppConst::BOOKING_ITEM_PENDING]);
        }

        if (! $mapping instanceof ScheduleCabinMapping) {
            return;
        }

        $merchantId = $this->resolveMerchantId($mapping);
        $itemId = (int) ($cabinLock->mapping_id ?: $mapping->id);
        broadcast(new CabinReleasedEvent(
            (int) $cabinLock->trip_id,
            $itemId,
            $this->mappingBroadcastData($mapping),
            null,
            $merchantId,
        ))->toOthers();
    }

    public function restored(CabinLock $cabinLock)
    {
        //
    }

    public function forceDeleted(CabinLock $cabinLock)
    {
        //
    }

    private function mappingBroadcastData(ScheduleCabinMapping $mapping): array
    {
        $mapping->loadMissing('cabinType', 'cabin');

        return $mapping->only(['cabin_id', 'fare']) + [
            'cabin_no' => strtoupper((string) ($mapping->cabinType?->letter ?? '')).$mapping->cabin?->cabin_no,
        ];
    }

    private function resolveMerchantId(ScheduleCabinMapping $mapping): ?int
    {
        $mid = $mapping->merchant_id;
        if ($mid !== null && (int) $mid > 0) {
            return (int) $mid;
        }
        $mapping->loadMissing('schedule');
        $s = $mapping->schedule?->merchant_id;

        return ($s !== null && (int) $s > 0) ? (int) $s : null;
    }
}
