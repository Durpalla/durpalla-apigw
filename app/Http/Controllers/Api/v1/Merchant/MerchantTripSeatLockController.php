<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Constants\AppConst;
use App\Events\TripPublicCartItemEvent;
use App\Http\Controllers\Controller;
use App\Models\BookingItem;
use App\Models\CabinLock;
use App\Models\ScheduleCabinMapping;
use App\Models\VehicleSchedule;
use App\Services\Transport\TransportSeatReferenceResolver;
use App\Support\ResolvesMerchantOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Merchant Desk Pro: lock/release seats before counter booking.
 *
 * Realtime: public `trip.{numericId}` + {@see TripPublicCartItemEvent} (`item.locked` / `item.released`) only (no duplicate presence `seat.*`).
 */
class MerchantTripSeatLockController extends Controller
{
    use ResolvesMerchantOwner;

    public function __construct(
        private readonly TransportSeatReferenceResolver $seatResolver,
    ) {}

    private function getOwnedTrip(Request $request, string $tripId): ?VehicleSchedule
    {
        $id = (int) preg_replace('/^TRIP-/', '', $tripId);
        $ownerId = $this->merchantOwnerId($request);

        return VehicleSchedule::query()
            ->where('id', $id)
            ->where('merchant_id', $ownerId)
            ->first();
    }

    private function merchantToken(Request $request): string
    {
        return base64_encode((string) $request->user()->email);
    }

    private function mappingAvailableForMerchantLock(Request $request, ScheduleCabinMapping $m, VehicleSchedule $trip): bool
    {
        if ((int) $m->schedule_id !== (int) $trip->id) {
            return false;
        }
        // Merchant "reserved" is an in-house hold — counter staff must still be able to lock for booking.
        if ($m->booked) {
            return false;
        }
        if (BookingItem::where(['cabin_id' => $m->cabin_id, 'trip_id' => $m->schedule_id, 'status' => AppConst::BOOKING_ITEM_ACTIVE])->exists()) {
            return false;
        }
        $myToken = $this->merchantToken($request);
        if ((int) $m->is_locked === 1) {
            $lock = CabinLock::where('mapping_id', $m->id)->first();
            if ($lock && $lock->customer_token !== $myToken) {
                return false;
            }
        }
        return true;
    }

    /**
     * POST /merchant/trips/{tripId}/seats/lock
     * Body: { seat_ids: ["E-A1", "123", ...] }
     */
    public function lock(Request $request, string $tripId): JsonResponse
    {
        $seatIds = $request->input('seat_ids', $request->input('seatIds', $request->input('items', [])));
        $validator = Validator::make(['seat_ids' => $seatIds], [
            'seat_ids' => 'required|array|min:1',
            'seat_ids.*' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $trip = $this->getOwnedTrip($request, $tripId);
        if (! $trip) {
            return response()->json(['success' => false, 'message' => 'Trip not found'], 404);
        }

        $token = $this->merchantToken($request);
        $locked = [];
        /** @var list<int> */
        $publicTripMappingIds = [];
        $errors = [];

        foreach ($seatIds as $raw) {
            $raw = is_string($raw) ? $raw : (string) $raw;
            $mapping = $this->seatResolver->resolveMapping($trip, $raw);
            if (! $mapping) {
                $errors[] = "{$raw}: unknown seat";
                continue;
            }
            if (! $this->mappingAvailableForMerchantLock($request, $mapping, $trip)) {
                $errors[] = "{$raw}: not available";
                continue;
            }
            try {
                $seatResolver = $this->seatResolver;
                DB::transaction(function () use ($mapping, $trip, $token, &$locked, &$publicTripMappingIds, $seatResolver) {
                    $mapping->refresh();
                    $broadcastId = $seatResolver->supervisorAppSeatId($mapping);
                    if ((int) $mapping->is_locked === 1) {
                        $existing = CabinLock::where('mapping_id', $mapping->id)->where('customer_token', $token)->first();
                        if ($existing) {
                            $locked[] = $broadcastId;
                            return;
                        }
                        if (! CabinLock::where('mapping_id', $mapping->id)->exists()) {
                            $mapping->update(['is_locked' => AppConst::BOOKING_ITEM_PENDING, 'lock_id' => null]);
                            $mapping->refresh();
                        }
                    }
                    $lock = CabinLock::create([
                        'cabin_id' => $mapping->cabin_id,
                        'mapping_id' => $mapping->id,
                        'customer_token' => $token,
                        'trip_id' => (int) $mapping->schedule_id,
                        'expire_at' => now()->addMinutes((int) config('constants.cart_expires', 15)),
                    ]);
                    $mapping->update(['is_locked' => 1, 'lock_id' => $lock->id]);
                    $locked[] = $broadcastId;
                    $publicTripMappingIds[] = (int) $mapping->id;
                }, 2);
            } catch (\Throwable $e) {
                $errors[] = "{$raw}: ".$e->getMessage();
            }
        }

        if (count($locked) === 0) {
            return response()->json([
                'success' => false,
                'message' => $errors[0] ?? 'Could not lock seats',
                'errors' => $errors,
            ], 422);
        }

        $tripSlug = 'TRIP-'.str_pad((string) $trip->id, 3, '0', STR_PAD_LEFT);
        $uid = ($u = $request->user()) ? (int) $u->id : null;
        foreach (array_unique($publicTripMappingIds) as $mid) {
            $m = ScheduleCabinMapping::query()
                ->with(['cabinType', 'cabin.cabinType', 'schedule'])
                ->find($mid);
            if ($m) {
                TripPublicCartItemEvent::broadcastSafely(TripPublicCartItemEvent::EVENT_LOCKED, (int) $trip->id, $m, $uid);
            }
        }
        Log::debug('merchant_seat_lock.broadcast', [
            'trip_slug' => $tripSlug,
            'channel' => 'trip.'.(int) $trip->id,
            'event' => TripPublicCartItemEvent::EVENT_LOCKED,
            'seats' => $locked,
        ]);

        return response()->json(['success' => true, 'locked' => $locked, 'errors' => $errors], 200);
    }

    /**
     * POST /merchant/trips/{tripId}/seats/release
     * Body: { seat_ids: ["E-A1", ...] }
     */
    public function release(Request $request, string $tripId): JsonResponse
    {
        $seatIds = $request->input('seat_ids', $request->input('seatIds', []));
        $validator = Validator::make(['seat_ids' => $seatIds], [
            'seat_ids' => 'required|array|min:1',
            'seat_ids.*' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $trip = $this->getOwnedTrip($request, $tripId);
        if (! $trip) {
            return response()->json(['success' => false, 'message' => 'Trip not found'], 404);
        }

        $token = $this->merchantToken($request);
        $tripSlug = 'TRIP-'.str_pad((string) $trip->id, 3, '0', STR_PAD_LEFT);
        $uid = ($u = $request->user()) ? (int) $u->id : null;
        foreach ($seatIds as $raw) {
            $raw = is_string($raw) ? $raw : (string) $raw;
            $mapping = $this->seatResolver->resolveMapping($trip, $raw);
            if (! $mapping) {
                continue;
            }
            $lock = CabinLock::where('mapping_id', $mapping->id)->where('customer_token', $token)->first();
            if ($lock) {
                $lock->delete();
            }
            DB::table('schedule_cabin_mappings')->where('id', $mapping->id)->update(['is_locked' => AppConst::BOOKING_ITEM_PENDING, 'lock_id' => null]);
            $m = ScheduleCabinMapping::query()
                ->with(['cabinType', 'cabin.cabinType', 'schedule'])
                ->find($mapping->id);
            if ($m) {
                TripPublicCartItemEvent::broadcastSafely(TripPublicCartItemEvent::EVENT_RELEASED, (int) $trip->id, $m, $uid);
            }
        }
        Log::debug('merchant_seat_release.broadcast', [
            'trip_slug' => $tripSlug,
            'channel' => 'trip.'.(int) $trip->id,
            'event' => TripPublicCartItemEvent::EVENT_RELEASED,
            'seat_ids' => $seatIds,
        ]);

        return response()->json(['success' => true], 200);
    }
}

