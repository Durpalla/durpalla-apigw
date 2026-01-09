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
        if($cabinLock->mapping_id) {
            ScheduleCabinMapping::where('id', $cabinLock->mapping_id)
                ->first()
                ->update(['is_locked' => AppConst::BOOKING_ITEM_ACTIVE]);
        } else {
            ScheduleCabinMapping::where(['cabin_id' => $cabinLock->cabin_id, 'schedule_id' => $cabinLock->trip_id])
                ->first()
                ->update(['is_locked' => AppConst::BOOKING_ITEM_ACTIVE]);
        }
        broadcast(new CabinLockedEvent(
            $cabinLock->trip_id,
            $cabinLock->mapping_id,
            $cabinLock->mapping->only(['cabin_id', 'fare']) + [
                'cabin_no' => strtoupper($cabinLock->mapping?->cabinType?->letter). $cabinLock->mapping?->cabin?->cabin_no
            ]
        ))->toOthers();
    }

    /**
     * Handle the cabin lock "updated" event.
     *
     * @param  CabinLock  $cabinLock
     * @return void
     */
    public function updated(CabinLock $cabinLock)
    {
        //
    }

    /**
     * Handle the cabin lock "deleted" event.
     *
     * @param  CabinLock  $cabinLock
     * @return void
     */
    public function deleted(CabinLock $cabinLock)
    {
        if($cabinLock->mapping_id) {
            ScheduleCabinMapping::find($cabinLock->mapping_id)
                ->update(['is_locked' => AppConst::BOOKING_ITEM_PENDING]);
        } else {
            ScheduleCabinMapping::where(['cabin_id' => $cabinLock->cabin_id, 'schedule_id' => $cabinLock->trip_id])
                ->first()
                ->update(['is_locked' => AppConst::BOOKING_ITEM_PENDING]);
        }

        broadcast(new CabinReleasedEvent(
            $cabinLock->trip_id,
            $cabinLock->mapping_id,
            $cabinLock->mapping->only(['cabin_id', 'fare']) + [
                'cabin_no' => strtoupper($cabinLock->mapping?->cabinType?->letter). $cabinLock->mapping?->cabin?->cabin_no
            ]
        ))->toOthers();
    }

    /**
     * Handle the cabin lock "restored" event.
     *
     * @param  CabinLock  $cabinLock
     * @return void
     */
    public function restored(CabinLock $cabinLock)
    {
        //
    }

    /**
     * Handle the cabin lock "force deleted" event.
     *
     * @param  CabinLock  $cabinLock
     * @return void
     */
    public function forceDeleted(CabinLock $cabinLock)
    {
        //
    }
}
