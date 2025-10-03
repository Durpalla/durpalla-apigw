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

            if (!$this->cart->validate($item)) {
                throw new \Exception(trans('Your selected item is not available or not eligible for booking'));
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
        //validation rules
        $validator = Validator::make($request->all(), [
            'lock_id' => 'bail|required|integer',
            'item_id' => 'bail|required|integer|exists:schedule_cabin_mappings,id'
        ]);

        //validation fails
        if ($validator->fails())
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], $this->success);

        $item = CabinLock::find($request->input('lock_id'));

        if ($item) {
            $item->delete();
            if(!$item->mapping->update(['is_locked' => false])) {
                DB::table('schedule_cabin_mappings')->where('id', $request->item_id)->update(['is_locked' => 0]);
            }
            $data['success'] = true;
            $data['index'] = (int) $request->index;
            $data['message'] = __('Your item has been successfully removed');
        } else {
            $data['success'] = true;
            $data['index'] = (int)$request->index;
            $data['message'] = __('Your item has been successfully removed');
        }

        return response()->json($data, $this->success);
    }
}
