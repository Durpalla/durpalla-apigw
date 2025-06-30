<?php
namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Cabin;
use App\Models\CabinLock;
use App\Models\VehicleSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ApiCartController extends Controller
{
    protected $success = 200;

    /**
     * Lock item for customer.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function lock( Request $request ): JsonResponse
    {
        $data = ['success' => false, 'message' => 'Your item cannot be locked'];
        //validation rules
        $validator = Validator::make($request->all(), [
            'item_id' => 'bail|required|integer|exists:cabins,id',
            'trip_id' => 'bail|required|integer|exists:vehicle_schedules,id',
            'customer_token' => 'bail|required|string'
        ]);

        //validation fails
        if ( $validator->fails() )
            return response()->json(['success'=> false, 'message' => $validator->errors()->first()], $this->success );

        $schedule = VehicleSchedule::with(['startingPoint.ghat', 'endingPoint.ghat', 'boardingVias.ghat'])->findOrFail( $request->trip_id );

        if( $schedule->schedule_date >= date('Y-m-d') ) {
            $item = Cabin::with(['launch.merchant', 'cabinType', 'books' => function($query) use($schedule) {
                $query->where(['trip_id' => $schedule->id])->whereIn('status', [0,1]);
            }, 'locks' => function($query) use($schedule) {
                $query->where('trip_id', $schedule->id);
            }])
            ->findOrFail( $request->item_id );

            if( $item->books && $item->books->count() > 0 ) {
                $data['message'] = 'Your ' . $item->type . ' is already booked';
            } elseif ( $item->locks && $item->locks->count() > 0 ) {
                $data['message'] = 'Your ' . $item->type . ' is already been locked';
            } else {

                try{
                    DB::transaction(function() use($request, $item, $schedule, &$data) {
                        $lockItem = CabinLock::create([
                            'cabin_id' => $item->id,
                            'customer_token' => ( string )$request->customer_token,
                            'trip_id' => ( int )$request->trip_id
                        ]);
                        $data['success'] = true;
                        $data['message'] = 'The item has been successfully locked';
                        $data['item'] = [
                            'trip_id' => $lockItem->trip_id,
                            'trip_date' => date('Y-m-d h:i:s', strtotime($schedule->leaving_at)),
                            'launch_id' => $item->vehicle_id,
                            'merchant_id' => $item->launch['merchant_id'],
                            'route_id' => $schedule->route_id,
                            'launch_name' => $item->launch['name'],
                            'route_name' => $schedule->startingPoint['ghat']['name'] . ' - ' . $schedule->endingPoint['ghat']['name'],
                            'cabin_type_id' => $item->type_id,
                            'cabin_floor' => $item->floor,
                            'cabin_no' => ($item['type'] == 'cabin') ? $item['cabinType']['letter'] . '-' . $item['cabin_no'] : $item['cabin_no'],
                            'vat_amount' => getOption('vat_amount', 0),
                            'vat_applicable_to' => $item['launch']['merchant']['vat_applicable_to'],
                            'description' => $item['cabinType']['name'] . ' - ' . $item['cabinType']['letter'] . '-' . $item['cabin_no'],
                            'item_type' => $item['cabinType']['name'],
                            'cabin_id' => $item->id,
                            'cabin_type' => $item->type,
                            'cabin_fare' => $item->fare,
                            'cabin_is_ac' => $item->cabinType['is_ac'],
                            'capacity' => $item->passenger_capacity,
                            'status' => 2,
                            'boardingPoint' => [],
                            'stoppages' => [],
                            'passenger' => ['type' => 'self', 'name' => '', 'mobile' => '', 'person' => $item->passenger_capacity]
                        ];

                        if ($schedule->schedule_type == 'reverse') {
                            $data['item']['route_name'] = $schedule->endingPoint['ghat']['name'] . ' - ' . $schedule->startingPoint['ghat']['name'];
                        }

                        //push stoppages
                        if ($schedule->schedule_type == 'reverse') {
                            $data['item']['boardingPoint'] = ['id' => $schedule->endingPoint['id'], 'name' => $schedule->endingPoint['ghat']['name']];
                            array_push($data['item']['stoppages'], ['id' => $schedule->endingPoint['id'], 'name' => $schedule->endingPoint['ghat']['name']]);
                        } else {
                            $data['item']['boardingPoint'] = ['id' => $schedule->startingPoint['id'], 'name' => $schedule->startingPoint['ghat']['name']];
                            array_push($data['item']['stoppages'], ['id' => $schedule->startingPoint['id'], 'name' => $schedule->startingPoint['ghat']['name']]);
                        }

                        if ($schedule->boardingVias) {
                            foreach ($schedule->boardingVias as $stoppage) {
                                array_push($data['item']['stoppages'], ['id' => $stoppage['id'], 'name' => $stoppage['ghat']['name']]);
                            }
                        }
                    }, 2);
                } catch(\Exception $e) {
                    $data['message'] = 'Something happened wrong. please try again later.';
                }
            }
        }
        return response()->json($data, $this->success);
    }

    public function resetLockdItems()
    {
        $data = ['success' => false, 'message' => 'Cannot reset items'];
        $ok = CabinLock::truncate();

        if( $ok ) {
            $data['success'] = true;
            $data['message'] = 'successfully reset items';
        }

        return response()->json($data, $this->success);
    }

    /**
     * Add item to cart, bassically lock the item for this customer.
     *
     * @return \Illuminate\Http\Response
     */
    public function remove( Request $request )
    {
        $data = ['success' => false, 'message' => 'Your item cannot be unlocked'];
        //validation rules
        $validator = Validator::make($request->all(), [
            'item_id' => 'bail|required|integer',
            'trip_id' => 'bail|required|integer',
            'customer_token' => 'bail|required|string'
        ]);

        //validation fails
        if ( $validator->fails() )
            return response()->json(['success'=> false, 'message' => $validator->errors()->first()], $this->success );

        $item = CabinLock::where( ['customer_token' => $request->customer_token, 'cabin_id' => ( int ) $request->item_id, 'trip_id' => (int) $request->trip_id] )->first();

        if( $item ) {
            $item->delete();
            $data['success'] = true;
            $data['index'] = (int) $request->index;
            $data['message'] = 'Your item has been successfully removed';
        } else {
            $data['success'] = true;
             $data['index'] = (int) $request->index;
            $data['message'] = 'Your item has been successfully removed';
        }

        return response()->json($data, $this->success);
    }
}
