<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\CabinLock;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\AddToCartApiRequest;
use App\Models\ScheduleCabinMapping;
use App\Services\CartService;
use App\Services\TripService;

class ApiCartController extends Controller
{
    protected $success = 200;
    private $cart;
    private TripService $tripService;

    public function __construct(CartService $cart, TripService $tripService)
    {
        $this->cart = $cart;
        $this->tripService = $tripService;
    }

    public function lock(AddToCartApiRequest $request): JsonResponse
    {
        $data = ['success' => false, 'message' => __('Your item cannot be locked')];

        try {
            $item = ScheduleCabinMapping::with(['cabinType', 'schedule.startFrom', 'schedule.stopTo', 'schedule.boardingVias.ghat', 'schedule.vehicle.merchant'])->findOrFail($request->item_id);

            $validation = $this->cart->validate($item);
            if ($validation !== true) {
                throw new \Exception($validation);
            }

            if ($this->cart->add($item)) {
                $item->refresh();
                $data['item'] = $this->cart->save($item);
                $data['success'] = true;
                $data['message'] = __('Your item has been added to cart');
            } else {
                throw new \Exception(trans('Opps! something went wrong.'));
            }

        } catch (\Exception $e) {
            $data['message'] = $e->getMessage();
        }
        return response()->json($data, $this->success);
    }

    public function resetLockdItems(): JsonResponse
    {
        $data = ['success' => false, 'message' => __('Cannot reset items')];
        try {
            CabinLock::get()
                ->each(function ($item, $key) {
                    $item->delete();
                });
            $data['success'] = true;
            $data['message'] = __('successfully reset items');
        } catch (\Exception $exception) {
            $data['message'] = $exception->getMessage();
        }

        return response()->json($data, $this->success);
    }

    public function remove(Request $request): JsonResponse
    {
        $data = ['success' => false, 'message' => __('Your item cannot be unlocked')];
        $validator = Validator::make($request->all(), [
            'item_id' => 'bail|required|integer|exists:schedule_cabin_mappings,id',
            'lock_id' => 'bail|nullable|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], $this->success);
        }

        $itemId = (int) $request->item_id;
        $lockId = $request->lock_id;
        $customerToken = $this->cart->getCurrentCustomerToken();

        $query = CabinLock::where('mapping_id', $itemId);
        if ($lockId) {
            $query->where('id', $lockId);
        }
        if ($customerToken !== null) {
            $query->where('customer_token', $customerToken);
        }
        $lock = $query->first();

        if (!$lock) {
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
}
