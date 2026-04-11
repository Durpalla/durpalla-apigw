<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScheduleCabinMapping;
use App\Models\CabinLock;
use App\Services\CartService;
use App\Services\BookingService;
use App\Services\TripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Refactored Transport API: search, lock, unlock, booking confirm.
 * Same paths and response shape as durpalla for customer app.
 */
class TransportApiController extends Controller
{
    protected int $success = 200;

    public function __construct(
        protected CartService $cartService,
        protected BookingService $bookingService,
        protected TripService $tripService
    ) {}

    /**
     * GET Search trips. Params: from_location_id, to_location_id, travel_date (or trip_from, trip_to, trip_date), vehicle_type, adults, children.
     */
    public function search(Request $request): JsonResponse
    {
        $trips = $this->tripService->getSearchTrip($request);

        return response()->json([
            'success' => true,
            'data' => $trips,
            'meta' => ['count' => is_array($trips) ? count($trips) : 0],
        ], $this->success);
    }

    /**
     * POST Lock one or more items (add to cart). Params: item_id (single) OR item_ids[] (array).
     */
    public function lock(Request $request): JsonResponse
    {
        $itemIds = $request->has('item_ids')
            ? (array) $request->input('item_ids')
            : [$request->input('item_id')];

        $validator = Validator::make(
            ['item_ids' => $itemIds],
            ['item_ids' => 'required|array', 'item_ids.*' => 'required|integer|exists:schedule_cabin_mappings,id']
        );
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], $this->success);
        }

        $locked = [];
        $errors = [];

        foreach ($itemIds as $index => $itemId) {
            try {
                $item = ScheduleCabinMapping::with([
                    'cabinType', 'schedule.startFrom', 'schedule.stopTo',
                    'schedule.boardingVias.ghat', 'schedule.vehicle.merchant',
                ])->findOrFail($itemId);

                $validation = $this->cartService->validate($item);
                if ($validation !== true) {
                    $errors[] = "Item {$itemId}: " . $validation;
                    continue;
                }

                if ($this->cartService->add($item)) {
                    $item->refresh();
                    $locked[] = $this->cartService->save($item);
                } else {
                    $errors[] = "Item {$itemId}: lock failed";
                }
            } catch (\Throwable $e) {
                $errors[] = "Item {$itemId}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => count($locked) > 0,
            'message' => count($locked) > 0
                ? (count($locked) === count($itemIds) ? __('Your items have been added to cart') : __('Some items added'))
                : ($errors[0] ?? __('Your item cannot be locked')),
            'data' => [
                'items' => $locked,
                'count' => count($locked),
                'errors' => $errors,
            ],
        ], $this->success);
    }

    /**
     * POST Unlock (remove from cart). Params: item_id (required), lock_id (optional), index (optional).
     */
    public function unlock(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'bail|required|integer|exists:schedule_cabin_mappings,id',
            'lock_id' => 'bail|nullable|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], $this->success);
        }

        $itemId = (int) $request->item_id;
        $lockId = $request->lock_id;
        $customerToken = $this->cartService->getCurrentCustomerToken();

        $query = CabinLock::where('mapping_id', $itemId);
        if ($lockId) {
            $query->where('id', $lockId);
        }
        if ($customerToken !== null) {
            $query->where('customer_token', $customerToken);
        }
        $lock = $query->first();

        if (! $lock) {
            return response()->json([
                'success' => false,
                'message' => __('Lock not found or you do not own this item'),
            ], $this->success);
        }

        $lock->delete();
        DB::table('schedule_cabin_mappings')->where('id', $itemId)->update(['is_locked' => 0, 'lock_id' => null]);

        return response()->json([
            'success' => true,
            'message' => __('Your item has been successfully removed'),
            'data' => ['item_id' => $itemId, 'index' => (int) $request->input('index', 0)],
        ], $this->success);
    }

    /**
     * POST Confirm booking from cart items.
     */
    public function confirm(Request $request): JsonResponse
    {
        $items = $request->input('items');
        if (!is_array($items)) {
            $items = json_decode(str_replace('\\', '', (string) $request->items), true) ?? [];
        }

        if (empty($items)) {
            return response()->json([
                'success' => false,
                'message' => __('Items are required'),
            ], $this->success);
        }

        $request->merge([
            'customer_name' => $request->input('customer_name', auth()->user()?->name),
            'customer_mobile' => $request->input('customer_mobile', auth()->user()?->mobile ?? auth('customer')->user()?->mobile),
            'paid_amount' => $request->input('paid_amount', 0),
            'payment_method' => $request->input('payment_method', 'cash'),
            'platform' => $request->input('platform', 'mobile'),
        ]);

        $cartItems = array_map(function ($item) {
            $item = (array) $item;
            return (object) array_merge([
                'item_id' => $item['item_id'] ?? null,
                'type' => $item['type'] ?? 'seat',
                'for_self' => $item['for_self'] ?? true,
                'passengers' => $item['passengers'] ?? [],
                'discount' => $item['discount'] ?? 0,
                'boardingPoint' => $item['boardingPoint'] ?? null,
                'meta' => $item['meta'] ?? [],
            ], $item);
        }, $items);

        $data = [];
        try {
            $this->bookingService->confirm($cartItems, $data);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $this->success);
        }

        return response()->json(array_merge([
            'success' => !empty($data['success']),
            'message' => $data['message'] ?? '',
        ], $data), $this->success);
    }
}
