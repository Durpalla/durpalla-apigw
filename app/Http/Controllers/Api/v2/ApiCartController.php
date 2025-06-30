<?php

namespace App\Http\Controllers\Api\v2;

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
    private $tripService;

    public function __construct(CartService $cart, TripService $tripService)
    {
        $this->cart = $cart;
        $this->tripService = $tripService;
    }

    /**
     * Lock item for customer.
     *
     * @param AddToCartApiRequest $request
     * @return JsonResponse
     */
    public function lock(AddToCartApiRequest $request): JsonResponse
    {
        $data = ['success' => false, 'message' => __('Your item cannot be locked')];

        try {
            $item = ScheduleCabinMapping::with(['cabinType', 'schedule.startFrom', 'schedule.stopTo', 'schedule.boardingVias.ghat', 'schedule.vehicle.merchant'])->findOrFail($request->item_id);

            if (!$this->cart->validate($item)) {
                throw new \Exception(trans('Your selected item is not available or not eligible for booking'));
            }

            if ($this->cart->add($item)) {
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

    /**
     * Add item to cart, bassically lock the item for this customer.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function remove(Request $request): JsonResponse
    {
        $data = ['success' => false, 'message' => __('Your item cannot be unlocked')];
        //validation rules
        $validator = Validator::make($request->all(), [
            'item_id' => 'bail|required|integer',
            'trip_id' => 'bail|required|integer'
        ]);

        //validation fails
        if ($validator->fails())
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], $this->success);

        $item = CabinLock::where(['cabin_id' => ( int )$request->item_id, 'trip_id' => (int)$request->trip_id])->first();

        DB::table('schedule_cabin_mappings')->where('id', $request->item_id)->update(['is_locked' => 0]);
        if ($item) {
            $item->delete();
            $data['success'] = true;
            $data['index'] = (int)$request->index;
            $data['message'] = __('Your item has been successfully removed');
        } else {
            $data['success'] = true;
            $data['index'] = (int)$request->index;
            $data['message'] = __('Your item has been successfully removed');
        }

        return response()->json($data, $this->success);
    }
}
